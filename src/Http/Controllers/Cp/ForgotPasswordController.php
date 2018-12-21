<?php

namespace Kilvin\Http\Controllers\Cp;

use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

/*
|--------------------------------------------------------------------------
| Password Reset Controller
|--------------------------------------------------------------------------
|
| This controller is responsible for handling password reset emails and
| includes a trait which assists in sending these notifications from
| your application to your users. Feel free to explore this trait.
|
*/

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

     /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showForgotPasswordForm()
    {
        return view('kilvin::cp.auth.forgot_password_form');
    }
}