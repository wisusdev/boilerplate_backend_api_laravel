<?php

namespace App\Services;

use App\Traits\ExternalConsumerServices;

class WompiService
{
	use ExternalConsumerServices;

	protected string $base_url;
	protected string $base_auth_url;
	protected string $public_key;
	protected string $private_key;

	public function __construct()
	{
		$this->base_url = config('services.wompi.base_uri');
		$this->base_auth_url = config('services.wompi.base_auth_uri');
		$this->public_key = config('services.wompi.public_key');
		$this->private_key = config('services.wompi.private_key');
	}

	private function getToken(): object
	{
		$response = $this->makeRequest(
			'POST',
			$this->base_auth_url . '/connect/token',
			[
				'grant_type' => 'client_credentials',
				'client_id' => $this->public_key,
				'client_secret' => $this->private_key,
				'audience' => config('app.name'),
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
			]
		);

		return json_decode($response);
	}

	public function getRegion(): object
	{
		$token = $this->getToken();

		$response = $this->makeRequest(
			'GET',
			$this->base_url . '/api/Regiones',
			[],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $token->access_token,
			]
		);

		return json_decode($response);
	}

	public function createPaymentWithCard(array $data, float $monto): object
	{
		$token = $this->getToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/TransaccionCompra/3DS',
			[
				"tarjetaCreditoDebido" => [
					"numeroTarjeta" => str_replace(' ', '', $data['card_number']),
					"cvv" => str_replace(' ', '', $data['cvv']),
					"mesVencimiento" => $data['expiration_month'],
					"anioVencimiento" => $data['expiration_year'],
				],
				"monto" => $monto,
				"urlRedirect" => config('app.frontend_url'),
				"nombre" => $data['first_name'],
				"apellido" => $data['last_name'],
				"email" => $data['email'],
				"idPais" => $data['country'],
				"idRegion" => $data['state'],
				"ciudad" => $data['city'],
				"direccion" => $data['address'],
				"codigoPostal" => $data['postal_code'],
				"telefono" => $data['phone'],
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $token->access_token,
			],
			true
		);

		return json_decode($response);

	}
}