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
use Piplin\Http\Requests\StoreReleaseRequest;
use Piplin\Models\Release;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller of releases.
 */
class ReleaseController extends Controller
{
    /**
     * Adds a release for the specified task.
     *
     * @param StoreReleaseRequest $request
     *
     * @return Response|array
     */
    public function store(StoreReleaseRequest $request)
    {
        $fields = $request->only(
            'name',
            'project_id',
            'task_id'
        );

        $release = Release::create($fields);

        return [
            'success' => true,
        ];
    }

    /**
     * Remove the specified file from storage.
     *
     * @param Release $release
     *
     * @return Response|array
     */
    public function destroy(Release $release)
    {
        $release->forceDelete();

        return [
            'success' => true,
        ];
    }
}
