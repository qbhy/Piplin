<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Http\Controllers\Profile;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Intervention\Image\Facades\Image;
use Piplin\Bus\Events\EmailChangeRequestedEvent;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreProfileRequest;
use Piplin\Http\Requests\StoreUserSettingsRequest;
use Piplin\Models\User;
use PragmaRX\Google2FA\Contracts\Google2FA as Google2FA;
use Symfony\Component\HttpFoundation\Response;

/**
 * The use profile controller.
 */
class ProfileController extends Controller
{
    /**
     * @var Google2fa
     */
    private $google2fa;

    /**
     * Class constructor.
     *
     * @param  Google2FA  $google2fa
     *
     * @return void
     */
    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * View user profile.
     *
     * @param  string  $tab
     *
     * @return Response|View
     */
    public function index($tab = 'basic')
    {
        $user = Auth::user();

        if (!in_array($tab, ['basic', 'settings', 'avatar', 'email', '2fa'], true)) {
            $tab = 'basic';
        }

        $code = $this->google2fa->generateSecretKey();
        if ($user->has_two_factor_authentication || old('google_code')) {
            $code = old('google_code', $user->google2fa_secret);
        }

        $img = $this->google2fa->getQRCodeGoogleUrl('Piplin', $user->email, $code);

        return view('profile.index', [
            'tab' => $tab ?: 'basic',
            'google_2fa_url' => $img,
            'google_2fa_code' => $code,
            'title' => trans('users.update_profile'),
        ]);
    }

    /**
     * Update user's basic profile.
     *
     * @param  StoreProfileRequest  $request
     *
     * @return Response
     */
    public function update(StoreProfileRequest $request)
    {
        $fields = $request->only(
            'nickname',
            'password'
        );

        if (array_key_exists('password', $fields)) {
            if (empty($fields['password'])) {
                unset($fields['password']);
            } else {
                $fields['password'] = bcrypt($fields['password']);
            }
        }

        Auth::user()->update($fields);

        return redirect()->to(route('profile'));
    }

    /**
     * Update user's settings.
     *
     * @param  StoreUserSettingsRequest  $request
     *
     * @return Response
     */
    public function settings(StoreUserSettingsRequest $request)
    {
        Auth::user()->update($request->only(
            'skin',
            'language',
            'dashboard'
        ));

        return redirect()->to(route('profile', ['tab' => 'settings']));
    }

    /**
     * Send email to change a new email.
     *
     * @param  Dispatcher  $dispatcher
     *
     * @return string
     */
    public function requestEmail(Dispatcher $dispatcher)
    {
        $dispatcher->dispatch(new EmailChangeRequestedEvent(Auth::user()));

        return 'success';
    }

    /**
     * Show the page to input the new email.
     *
     * @param  string  $token
     *
     * @return View
     */
    public function email($token)
    {
        $user = User::where('email_token', $token)->first();

        return view('profile.change-email', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Change the user's email.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function changeEmail(Request $request)
    {
        $user = User::where('email_token', $request->get('token'))->first();

        if ($request->get('email')) {
            $user->email = $request->get('email');
            $user->email_token = '';

            $user->save();
        }

        return redirect()->to(route('profile', ['tab' => 'email']));
    }

    /**
     * Upload file.
     *
     * @param  Request  $request
     *
     * @return Response|array|string
     */
    public function upload(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|image',
        ]);

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            $path = '/upload/'.date('Y-m-d');
            $destinationPath = public_path().$path;
            $filename = uniqid().'.'.$file->getClientOriginalExtension();

            $file->move($destinationPath, $filename);

            return [
                'image' => url($path.'/'.$filename),
                'path' => $path.'/'.$filename,
                'message' => 'success',
            ];
        } else {
            return 'failed';
        }
    }

    /**
     * Reset the user's avatar to gravatar.
     *
     * @return Response|array
     */
    public function gravatar()
    {
        $user = Auth::user();
        $user->avatar = null;
        $user->save();

        return [
            'image' => $user->avatar_url,
            'success' => true,
        ];
    }

    /**
     * Set and crop the avatar.
     *
     * @param  Request  $request
     *
     * @return Response|array
     */
    public function avatar(Request $request)
    {
        $path = $request->get('path', '/img/cropper.jpg');
        $image = Image::make(public_path().$path);
        $rotate = $request->get('dataRotate');

        if ($rotate) {
            $image->rotate($rotate);
        }

        $width = $request->get('dataWidth');
        $height = $request->get('dataHeight');
        $left = $request->get('dataX');
        $top = $request->get('dataY');

        $image->crop($width, $height, $left, $top);
        $path = '/upload/'.date('Y-m-d').'/avatar'.uniqid().'.jpg';

        $image->save(public_path().$path);

        $user = Auth::user();
        $user->avatar = $path;
        $user->save();

        return [
            'image' => url($path),
            'success' => true,
        ];
    }

    /**
     * Activates two factor authentication.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function twoFactor(Request $request)
    {
        $secret = null;
        if ($request->has('two_factor')) {
            $secret = $request->get('google_code');

            if (!$this->google2fa->verifyKey($secret, $request->get('2fa_code'))) {
                $secret = null;

                return redirect()->back()
                    ->withInput($request->only('google_code', 'two_factor'));
            }
        }

        $user = Auth::user();
        $user->google2fa_secret = $secret;
        $user->save();

        return redirect()->to(route('profile', ['tab' => '2fa']));
    }
}
