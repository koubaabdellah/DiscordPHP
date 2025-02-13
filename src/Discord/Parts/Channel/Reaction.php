<?php

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-present David Cole <david.cole1340@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Parts\Channel;

use Discord\Helpers\Collection;
use Discord\Http\Endpoint;
use Discord\Parts\Guild\Emoji;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Part;
use Discord\Parts\User\User;
use React\Promise\ExtendedPromiseInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Discord\normalizePartId;
use function React\Promise\resolve;

/**
 * Represents a reaction to a message by members(s).
 *
 * @link https://discord.com/developers/docs/resources/channel#reaction-object
 *
 * @since 5.0.0
 *
 * @property int   $count Number of reactions.
 * @property bool  $me    Whether the current bot has reacted.
 * @property Emoji $emoji The emoji that was reacted with.
 *
 * @property      string         $channel_id The channel ID that the message belongs in.
 * @property-read Channel|Thread $channel    The channel that the message belongs to.
 * @property      string         $message_id The message ID the reaction is for.
 * @property      Message|null   $message    The message the reaction is for.
 * @property      string|null    $guild_id   The guild ID of the guild that owns the channel the message belongs in.
 * @property-read Guild|null     $guild      The guild that owns the channel the message belongs in.
 *
 * @property-read string $id The identifier of the reaction.
 */
class Reaction extends Part
{
    /**
     * @inheritDoc
     */
    protected $fillable = [
        'count',
        'me',
        'emoji',

        // events only
        'channel_id',
        'message_id',
        'guild_id',
    ];

    /**
     * @inheritDoc
     */
    public function isPartial(): bool
    {
        return $this->message === null;
    }

    /**
     * @inheritDoc
     */
    public function fetch(): ExtendedPromiseInterface
    {
        return $this->http->get(Endpoint::bind(Endpoint::CHANNEL_MESSAGE, $this->channel_id, $this->message_id))
            ->then(function ($message) {
                $this->message = $this->factory->part(Message::class, (array) $message + ['guild_id' => $this->guild_id], true);

                return $this;
            });
    }

    /**
     * Gets the emoji identifier, combination of `id` and `name`.
     *
     * @return string
     */
    protected function getIdAttribute(): string
    {
        if ($this->emoji->id === null) {
            return $this->emoji->name;
        }

        return ":{$this->emoji->name}:{$this->emoji->id}";
    }

    /**
     * Gets the users that have used the reaction.
     *
     * @link https://discord.com/developers/docs/resources/channel#get-reactions

     * @param array $options See https://discord.com/developers/docs/resources/channel#get-reactions
     *
     * @return ExtendedPromiseInterface<Collection|Users[]>
     */
    public function getUsers(array $options = []): ExtendedPromiseInterface
    {
        $query = Endpoint::bind(Endpoint::MESSAGE_REACTION_EMOJI, $this->channel_id, $this->message_id, urlencode($this->id));

        $resolver = new OptionsResolver();
        $resolver
            ->setDefined(['after', 'limit'])
            ->setAllowedTypes('after', ['int', 'string', User::class])
            ->setAllowedTypes('limit', 'int')
            ->setNormalizer('after', normalizePartId())
            ->setAllowedValues('limit', range(1, 100));

        $options = $resolver->resolve($options);

        foreach ($options as $key => $value) {
            $query->addQuery($key, $value);
        }

        return $this->http->get($query)
        ->then(function ($response) {
            $users = Collection::for(User::class);

            foreach ((array) $response as $user) {
                if (! $part = $this->discord->users->get('id', $user->id)) {
                    $part = $this->factory->part(User::class, (array) $user, true);
                }

                $users->pushItem($part);
            }

            return $users;
        });
    }

    /**
     * Gets all the users that have used this reaction.
     * Wrapper of the lower-level getUsers() function.
     *
     * @see Message::getUsers()
     *
     * @return ExtendedPromiseInterface<Collection|Users[]>
     */
    public function getAllUsers(): ExtendedPromiseInterface
    {
        $response = Collection::for(User::class);
        $getUsers = function ($after = null) use (&$getUsers, $response) {
            $options = ['limit' => 100];
            if ($after != null) {
                $options['after'] = $after;
            }

            return $this->getUsers($options)->then(function (Collection $users) use ($response, &$getUsers) {
                $last = null;
                foreach ($users as $user) {
                    $response->pushItem($user);
                    $last = $user;
                }

                if ($users->count() < 100) {
                    return resolve($response);
                }

                return $getUsers($last);
            });
        };

        return $getUsers();
    }

    /**
     * Gets the partial emoji attribute.
     *
     * @return Emoji|null
     */
    protected function getEmojiAttribute(): ?Emoji
    {
        if (! isset($this->attributes['emoji'])) {
            return null;
        }

        return $this->factory->part(Emoji::class, (array) $this->attributes['emoji'] + ['guild_id' => $this->guild_id], true);
    }

    /**
     * Gets the message attribute.
     *
     * @return Message|null
     */
    protected function getMessageAttribute(): ?Message
    {
        if ($channel = $this->channel) {
            return $channel->messages->get('id', $this->message_id);
        }

        return $this->attributes['message'] ?? null;
    }

    /**
     * Gets the channel attribute.
     *
     * @return Channel|Thread
     */
    protected function getChannelAttribute()
    {
        if ($channel = $this->discord->getChannel($this->channel_id)) {
            return $channel;
        }

        if ($guild = $this->guild) {
            foreach ($guild->channels as $channel) {
                if ($thread = $channel->threads->get('id', $this->channel_id)) {
                    return $thread;
                }
            }
        }

        return $this->factory->part(Channel::class, [
            'id' => $this->channel_id,
            'type' => Channel::TYPE_DM,
        ]);
    }

    /**
     * Returns the guild that owns the channel the message was sent in.
     *
     * @return Guild|null
     */
    protected function getGuildAttribute(): ?Guild
    {
        if (! isset($this->guild_id)) {
            return null;
        }

        return $this->discord->guilds->get('id', $this->guild_id);
    }
}
