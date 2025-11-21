<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LaporanHarianMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $tanggal;
    public $outlet;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function build()
    {
        return $this->subject('Laporan Harian ' . $this->tanggal)
            ->view('emails.laporan_harian');
    }
}
