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
use Discord\Parts\Interactions\Command\Overwrite;
use Discord\Repository\AbstractRepository;

/**
 * Contains permission overwrites for application guild commands.
 *
 * @see \Discord\Parts\Interactions\Command\Overwrite
 * @see \Discord\Parts\Interactions\Command\Command
 */
class OverwriteRepository extends AbstractRepository
{
    /**
     * @inheritdoc
     */
    protected $endpoints = [
        'all' => Endpoint::GUILD_APPLICATION_COMMANDS_PERMISSIONS,
        'get' => Endpoint::GUILD_APPLICATION_COMMAND_PERMISSIONS,
    ];

    /**
     * @inheritdoc
     */
    protected $class = Overwrite::class;
}
