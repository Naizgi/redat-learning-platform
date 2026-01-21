<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentDenied extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;
    public $denialReason;

    public function __construct(array $mailData, $denialReason)
    {
        $this->mailData = $mailData;
        $this->denialReason = $denialReason;
    }

    public function build()
    {
        return $this->subject('Payment Requires Attention - Action Required | Redat Learning Hub')
                    ->view('emails.payment-denied')
                    ->with(array_merge($this->mailData, [
                        'denial_reason' => $this->denialReason
                    ]));
    }
}