<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SendOtpMail extends Mailable
{
    public function __construct(public string $otp) {}

    public function build()
    {
        return $this->subject('Email Verification OTP')
            ->view('emails.otp')
            ->with(['otp' => $this->otp]);
    }
}
