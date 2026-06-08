<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $hoTen,
        public readonly string $otpCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NamBo Specialties - Mã xác thực tài khoản',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'hoTen'   => $this->hoTen,
                'otpCode' => $this->otpCode,
            ],
        );
    }
}
