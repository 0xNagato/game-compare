<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\RegionPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PriceAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Alert $alert,
        public RegionPrice $price,
        public array $context = []
    ) {
        $this->onQueue('notify');
    }

    public function build(): self
    {
        $product = $this->price->skuRegion->product;
        $skuRegion = $this->price->skuRegion;

        return $this->subject(sprintf('Price Alert: %s (%s)', $product->name, $skuRegion->region_code))
            ->markdown('emails.alerts.price', [
                'alert' => $this->alert,
                'price' => $this->price,
                'product' => $product,
                'skuRegion' => $skuRegion,
                'context' => $this->context,
            ]);
    }
}
