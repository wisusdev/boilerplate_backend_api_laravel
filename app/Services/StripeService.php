<?php

namespace App\Services;

use App\Traits\ExternalConsumerServices;

class StripeService
{

	use ExternalConsumerServices;

	protected string $base_url;
	protected string $client_id;
	protected string $client_secret;

	public function __construct()
	{
		$this->base_url = config('services.stripe.base_uri');
		$this->client_id = config('services.stripe.key');
		$this->client_secret = config('services.stripe.secret');
	}

	public function createProduct(string $name, string $description, string $type = 'service'): object
	{
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/products',
			[
				'name' => $name,
				'description' => $description,
				'type' => $type,
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			],
		);

		return json_decode($response);
	}

	public function updateProduct(string $name, string $description, string $productId): string
	{
		return $this->makeRequest(
			'POST',
			$this->base_url . '/v1/products/' . $productId,
			[
				'name' => $name,
				'description' => $description,
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			],
		);
	}

	public function createPlan(string $nickname, string $productId, int $amount, string $currency, string $interval): object
	{
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/plans',
			[
				'currency' => $currency,
				'interval' => $interval,
				'product' => $productId,
				'nickname' => $nickname,
				'amount' => $this->convertToCents($amount), // 20.00 equivalent to 2000
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			],
		);

		return json_decode($response);
	}

	public function updatePlan(bool $active, string $planId): string
	{
		return $this->makeRequest(
			'POST',
			$this->base_url . '/v1/plans/' . $planId,
			[
				'active' => $active,
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			],
		);
	}

	public function createPrice(string $productId, int $amount, string $currency, string $interval, int $intervalCount){
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/prices',
			[
				'currency' => $currency,
				'unit_amount' => $this->convertToCents($amount), // 20.00 equivalent to 2000
				'recurring[interval]' => $interval,
				'recurring[interval_count]' => $intervalCount,
				'product' => $productId,
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			],
		);

		return json_decode($response);
	}

	public function createCustomer(string $name, string $email, string $paymentMethod): object
	{
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/customers',
			[
				'name' => $name,
				'email' => $email,
				'payment_method' => $paymentMethod,
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			]
		);

		return json_decode($response);
	}

	public function createSubscription($customerId, $paymentMethod, $priceId): object
	{
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/subscriptions',
			[
				'customer' => $customerId,
				'items' => [
					[
						'price' => $priceId,
					],
				],
				'default_payment_method' => $paymentMethod,
				'expand' => ['latest_invoice.payment_intent']
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			]
		);

		return json_decode($response);
	}

	public function cancelSubscription(string $subscriptionId, string $reason): object
	{
		$response = $this->makeRequest(
			'DELETE',
			$this->base_url . '/v1/subscriptions/' . $subscriptionId,
			[],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->client_secret,
			]
		);

		return json_decode($response);
	}

	private function convertToCents($amount): int
	{
		$formattedAmount = number_format($amount, 2, '.', '');
		$cents = $formattedAmount * 100;
		return (int) $cents;
	}
}