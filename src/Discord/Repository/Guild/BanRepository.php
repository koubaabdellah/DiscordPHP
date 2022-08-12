<?php

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-present David Cole <david.cole1340@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Repository\Guild;

use Discord\Http\Endpoint;
use Discord\Parts\Guild\Ban;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Repository\AbstractRepository;
use React\Promise\ExtendedPromiseInterface;

/**
 * Contains bans on users.
 *
 * @see \Discord\Parts\Guild\Ban
 * @see \Discord\Parts\Guild\Guild
 */
class BanRepository extends AbstractRepository
{
    /**
     * @inheritdoc
     */
    protected $discrim = 'user_id';

    /**
     * @inheritdoc
     */
    protected $endpoints = [
        'all' => Endpoint::GUILD_BANS,
        'get' => Endpoint::GUILD_BAN,
        'delete' => Endpoint::GUILD_BAN,
    ];

    /**
     * @inheritdoc
     */
    protected $class = Ban::class;

    /**
     * Bans a member from the guild.
     *
     * @see https://discord.com/developers/docs/resources/guild#create-guild-ban
     *
     * @param User|Member|string $user
     * @param int|null           $daysToDeleteMessages
     * @param string|null        $reason
     *
     * @return ExtendedPromiseInterface
     */
    public function ban($user, ?int $daysToDeleteMessages = null, ?string $reason = null): ExtendedPromiseInterface
    {
        $content = [];
        $headers = [];

        if ($user instanceof Member) {
            $user = $user->user;
        } elseif (! ($user instanceof User)) {
            $user = $this->factory->part(User::class, ['id' => $user], true);
        }

        if (isset($daysToDeleteMessages)) {
            $content['delete_message_days'] = $daysToDeleteMessages;
        }

        if (isset($reason)) {
            $headers['X-Audit-Log-Reason'] = $reason;
        }

        return $this->http->put(
            Endpoint::bind(Endpoint::GUILD_BAN, $this->vars['guild_id'], $user->id),
            empty($content) ? null : $content,
            $headers
        )->then(function () use ($user, $reason) {
            $ban = $this->factory->part(Ban::class, [
                'user' => (object) $user->getRawAttributes(),
                'reason' => $reason,
                'guild_id' => $this->vars['guild_id'],
            ], true);

            return $this->cache->set($ban->user_id, $ban)->then(function () use ($ban) {
                return $ban;
            });
        });
    }

    /**
     * Unbans a member from the guild.
     *
     * @see https://discord.com/developers/docs/resources/guild#remove-guild-ban
     *
     * @param User|Ban|string $ban    User or Ban Part, or User ID
     * @param string|null     $reason Reason for Audit Log.
     *
     * @return ExtendedPromiseInterface
     */
    public function unban($ban, ?string $reason = null): ExtendedPromiseInterface
    {
        if ($ban instanceof User || $ban instanceof Member) {
            $ban = $ban->id;
        }

        if (is_scalar($ban)) {
            return $this->cache->get($ban)->then(function ($ban) use ($reason) {
                return $this->delete($ban, $reason);
            });
        }

        return $this->delete($ban, $reason);
    }
}
