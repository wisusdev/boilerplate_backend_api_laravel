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
		$this->client_id = config('services.stripe.client_id');
		$this->client_secret = config('services.stripe.client_secret');
	}

	public function createCustomer(string $name, string $email, string $paymentMethod): string
	{
		return $this->makeRequest(
			'POST',
			$this->base_url . '/v1/customers',
			[
				'name' => $name,
				'email' => $email,
				'payment_method' => $paymentMethod,
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->client_secret,
			],
			true
		);
	}

	public function createSubscription($customerId, $paymentMethod, $priceId): string
	{
		return $this->makeRequest(
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
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->client_secret,
			],
			true
		);
	}
}