<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $otp;
    public $userName;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $userName = null)
    {
        $this->otp = $otp;
        $this->userName = $userName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Account - Redat Learning Platform',
            from: new Address(
                env('MAIL_FROM_ADDRESS', 'noreply@redatlearninghub.com'),
                env('MAIL_FROM_NAME', 'Redat Learning Platform')
            ),
            // Add replyTo to improve sender reputation
            replyTo: [
                new Address('support@redatlearninghub.com', 'Redat Support')
            ],
            // Add tags for email categorization
            tags: ['otp', 'verification', 'authentication'],
            metadata: [
                'user_id' => 'otp_verification',
                'email_type' => 'transactional',
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp,
                'userName' => $this->userName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message with additional headers.
     * This method is automatically called by Laravel.
     */
    public function build()
    {
        return $this
            ->view('emails.otp')
            ->with([
                'otp' => $this->otp,
                'userName' => $this->userName,
            ])
            // Add headers to improve deliverability
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                
                // Add List-Unsubscribe header (reduces spam complaints)
                $headers->addTextHeader(
                    'List-Unsubscribe', 
                    '<mailto:support@redatlearninghub.com?subject=Unsubscribe>, <https://redatlearninghub.com/unsubscribe>'
                );
                
                // Mark as transactional, not promotional
                $headers->addTextHeader('X-Priority', '1');
                $headers->addTextHeader('X-Mailer', 'Redat Learning Platform');
                $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                $headers->addTextHeader('Precedence', 'bulk');
                
                // Add authentication headers
                $headers->addTextHeader('X-Entity-Ref-ID', 'otp-' . time() . '-' . $this->otp);
            });
    }
}