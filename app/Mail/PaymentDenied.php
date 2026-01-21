<?php
// app/Mail/PaymentDenied.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentDenied extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $denialReason;
    public $mailData;

    public function __construct(Payment $payment, string $denialReason, array $mailData = [])
    {
        $this->payment = $payment;
        $this->denialReason = $denialReason;
        $this->mailData = $mailData;
    }

    public function build()
    {
        return $this->subject('Payment Review Update - Action Required')
                    ->view('emails.payment-denied')
                    ->with(array_merge($this->mailData, [
                        'denial_reason' => $this->denialReason
                    ]));
    }
}