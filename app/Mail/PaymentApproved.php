<?php
// app/Mail/PaymentApproved.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $mailData;

    public function __construct(Payment $payment, array $mailData = [])
    {
        $this->payment = $payment;
        $this->mailData = $mailData;
    }

    public function build()
    {
        return $this->subject('Payment Approved - Your Subscription is Now Active')
                    ->view('emails.payment-approved')
                    ->with($this->mailData);
    }
}