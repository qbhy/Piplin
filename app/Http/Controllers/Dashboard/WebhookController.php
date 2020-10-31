<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Http\Controllers\Dashboard;

use Piplin\Http\Controllers\Controller;
use Piplin\Models\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 * The deployment webhook management controller.
 */
class WebhookController extends Controller
{
    /**
     * Generates a new webhook URL.
     *
     * @param Project $project
     * @param string  $type
     *
     * @return Response|array
     */
    public function refresh(Project $project, $type = '')
    {
        $project->generateHash();
        $project->save();

        return [
            'url' => route($type == 'build' ? 'webhook.build' : 'webhook.deploy', $project->hash),
        ];
    }
}
