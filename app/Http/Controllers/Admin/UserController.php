<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Http\Controllers\Admin;

use Illuminate\View\View;
use Piplin\Bus\Events\UserWasCreatedEvent;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreUserRequest;
use Piplin\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * User management controller.
 */
class UserController extends Controller
{
    /**
     * Display a listing of the users.
     *
     * @return Response|View
     */
    public function index()
    {
        $users = User::orderBy('id', 'desc')
            ->paginate(config('piplin.items_per_page', 10));

        return view('admin.users.index', [
            'title' => trans('users.manage'),
            'users_raw' => $users,
            'users' => $users->toJson(),
            'levels' => [
                User::LEVEL_COLLABORATOR => trans('users.level.collaborator'),
                User::LEVEL_MANAGER => trans('users.level.manager'),
                User::LEVEL_ADMIN => trans('users.level.admin'),
            ],
            'current_child' => 'users',
        ]);
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  StoreUserRequest  $request
     *
     * @return Response|User
     */
    public function store(StoreUserRequest $request)
    {
        $fields = $request->only(
            'name',
            'level',
            'nickname',
            'email',
            'password'
        );
        $fields['password'] = bcrypt($fields['password']);

        $user = User::create($fields);

        event(new UserWasCreatedEvent($user, $request->get('password')));

        return $user;
    }

    /**
     * Update the specified user in storage.
     *
     * @param  User              $user
     * @param  StoreUserRequest  $request
     *
     * @return Response|User
     */
    public function update(User $user, StoreUserRequest $request)
    {
        $fields = $request->only(
            'name',
            'level',
            'nickname',
            'email',
            'password'
        );

        if (array_key_exists('password', $fields)) {
            if (empty($fields['password'])) {
                unset($fields['password']);
            } else {
                $fields['password'] = bcrypt($fields['password']);
            }
        }

        $user->update($fields);

        return $user;
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  User  $user
     *
     * @return Response|array
     */
    public function destroy(User $user)
    {
        $user->delete();

        return [
            'success' => true,
        ];
    }
}
