<?php

namespace App\Jobs;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOtpEmail implements ShouldQueue
{
    use Queueable;

    public User $user;
    public string $otp;
    public string $language;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $otp, string $language = 'ar')
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->language = $language;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(
            new OtpMail($this->user, $this->otp, $this->language)
        );
    }
}
