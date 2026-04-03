<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'menu_category_id' => $this->menu_category_id,
            'name'             => $this->name,
            'description'      => $this->description,
            'price'            => $this->price,
            'currency'         => $this->currency,
            'image_url'        => $this->image_url,
            'is_available'     => $this->is_available,
            'sort_order'       => $this->sort_order,
            'formatted_price'  => $this->formattedPrice(),
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
