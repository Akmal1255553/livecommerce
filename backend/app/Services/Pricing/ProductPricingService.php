<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\CouponServiceInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\Contracts\Services\ShippingCalculatorInterface;
use App\DTOs\Cart\CartLinePricing;
use App\DTOs\Cart\CartPricingResult;
use App\DTOs\Cart\CouponApplication;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\ValueObjects\Money;
use Illuminate\Validation\ValidationException;

class ProductPricingService implements PricingServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CouponServiceInterface $coupons,
        private readonly ShippingCalculatorInterface $shipping,
    ) {}

    public function priceLines(array $lines): CartPricingResult
    {
        $pricedLines = [];
        $subtotal = Money::zero();
        $discountTotal = Money::zero();
        $itemCount = 0;

        foreach ($lines as $line) {
            $product = $this->products->findCatalogProduct($line['product_id']);

            if ($product === null) {
                continue;
            }

            $variant = null;

            if ($line['variant_id'] !== null) {
                $variant = $product->variants->firstWhere('id', $line['variant_id']);

                if ($variant === null) {
                    continue;
                }
            }

            $priced = $this->priceSingleLine(
                $line['item_id'],
                $product,
                $variant,
                (int) $line['quantity'],
            );

            $pricedLines[] = $priced;
            $subtotal = $subtotal->add($priced->lineTotal);
            $discountTotal = $discountTotal->add($priced->discountAmount);
            $itemCount += $priced->quantity;
        }

        $coupon = $this->coupons->resolve(null, new CartPricingResult(
            $pricedLines,
            $subtotal,
            $discountTotal,
            Money::zero(),
            $itemCount,
        ));

        $shipping = $this->shipping->calculate(new CartPricingResult(
            $pricedLines,
            $subtotal,
            $discountTotal,
            Money::zero(),
            $itemCount,
        ));

        return new CartPricingResult(
            lines: $pricedLines,
            subtotal: $subtotal,
            discountTotal: $discountTotal->add($coupon->discount),
            shippingEstimate: $shipping,
            itemCount: $itemCount,
        );
    }

    public function priceCart(Cart $cart): CartPricingResult
    {
        $lines = $cart->items->map(fn ($item) => [
            'item_id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
        ])->all();

        return $this->priceLines($lines);
    }

    public function calculateShippingSubtotal(CartPricingResult $pricing, CouponApplication $coupon): Money
    {
        return $pricing->subtotal
            ->subtract($pricing->discountTotal)
            ->add($pricing->shippingEstimate);
    }

    private function priceSingleLine(
        string|int $itemId,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
    ): CartLinePricing {
        $unitAmount = $this->unitAmount($product, $variant);
        $unitPrice = Money::uzs($unitAmount);
        $lineTotal = $unitPrice->multiply($quantity);

        $discountPerUnit = 0;

        if ($product->compare_at_price !== null) {
            $compareAt = (int) round((float) $product->compare_at_price);
            $adjustment = $variant !== null ? (int) round((float) $variant->price_adjustment) : 0;
            $compareUnit = $compareAt + $adjustment;

            if ($compareUnit > $unitAmount) {
                $discountPerUnit = $compareUnit - $unitAmount;
            }
        }

        $discountAmount = Money::uzs($discountPerUnit * $quantity);

        return new CartLinePricing(
            itemId: $itemId,
            product: $product,
            variant: $variant,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            discountAmount: $discountAmount,
        );
    }

    private function unitAmount(Product $product, ?ProductVariant $variant): int
    {
        $base = (int) round((float) $product->price);
        $adjustment = $variant !== null ? (int) round((float) $variant->price_adjustment) : 0;
        $total = $base + $adjustment;

        if ($total < 0) {
            throw ValidationException::withMessages([
                'price' => ['Invalid product price.'],
            ]);
        }

        return $total;
    }
}
