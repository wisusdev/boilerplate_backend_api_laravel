<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\CustomInquiry;
use App\Models\Invoice;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourAvailability;
use App\Models\TransportBookingDetail;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Database\Seeder;

class TravelModuleSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->take(5)->get();
        if ($users->isEmpty()) {
            return;
        }

        $this->seedCurrencies();
        $this->seedCategories();

        $tours    = $this->seedTours();
        $vehicles = $this->seedVehicles();

        // Bypass observer to avoid sending notification emails during seeding
        $dispatcher = Booking::getEventDispatcher();
        Booking::unsetEventDispatcher();

        try {
            $this->seedTourBookings($tours, $users);
            $this->seedTransportBookings($vehicles, $users);
        } finally {
            Booking::setEventDispatcher($dispatcher);
        }

        $this->seedCustomInquiries($users);
        $this->seedReviews();
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1, 'is_default' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'rate_to_usd' => 1.08, 'is_default' => false],
            ['code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => 'BTC', 'rate_to_usd' => 65000, 'is_default' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                array_merge($currency, ['is_active' => true])
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Volcanes', 'color' => '#184ca0', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Playa', 'color' => '#00a9e0', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Cultura', 'color' => '#ffcc05', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Trekking', 'color' => '#0f172a', 'is_active' => true, 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            TourCategory::query()->updateOrCreate(['name' => $category['name']], $category);
        }
    }

    private function seedTours(): \Illuminate\Support\Collection
    {
        $categories = TourCategory::query()->pluck('id', 'name');

        $toursData = [
            [
                'title'         => 'Volcan Santa Ana Sunrise',
                'description'   => 'Hike to the Santa Ana crater with local guide and breakfast included.',
                'price'         => 65,
                'max_capacity'  => 14,
                'location'      => 'Santa Ana',
                'category_id'   => $categories['Volcanes'] ?? null,
                'currency_code' => 'USD',
                'itinerary'     => ['Pickup 4:30 AM', 'Hike to crater', 'Breakfast stop'],
                'highlights'    => ['Crater views', 'Local guide', 'Photos included'],
                'map_url'       => 'https://maps.google.com/?q=Volcan+de+Santa+Ana',
                'faqs'          => [['q' => 'Need hiking shoes?', 'a' => 'Recommended for safety.']],
            ],
            [
                'title'         => 'Ruta de las Flores Cultural Day',
                'description'   => 'Coffee towns, murals, market food and local storytelling.',
                'price'         => 58,
                'max_capacity'  => 20,
                'location'      => 'Ahuachapan',
                'category_id'   => $categories['Cultura'] ?? null,
                'currency_code' => 'USD',
                'itinerary'     => ['Nahuizalco', 'Juayua market', 'Ataco murals'],
                'highlights'    => ['Coffee tasting', 'Street food', 'Colonial towns'],
                'map_url'       => 'https://maps.google.com/?q=Ruta+de+las+Flores',
                'faqs'          => [['q' => 'Family friendly?', 'a' => 'Yes, all ages are welcome.']],
            ],
            [
                'title'         => 'El Tunco Surf Session',
                'description'   => 'Beginner-friendly surf class with board rental and beach transfer.',
                'price'         => 47,
                'max_capacity'  => 12,
                'location'      => 'La Libertad',
                'category_id'   => $categories['Playa'] ?? null,
                'currency_code' => 'USD',
                'itinerary'     => ['Beach arrival', 'Warmup', 'Surf lesson', 'Lunch'],
                'highlights'    => ['Surf instructor', 'Board included', 'Oceanfront lunch'],
                'map_url'       => 'https://maps.google.com/?q=El+Tunco',
                'faqs'          => [['q' => 'Can non-swimmers join?', 'a' => 'Yes, with safety briefing and shallow-zone practice.']],
            ],
        ];

        // Tours adicionales generados para probar paginación y vista móvil con muchos items.
        $toursData = array_merge($toursData, $this->bulkTours(50, $categories));

        // map_url como coordenadas "lat,lng" para que el mapa de OpenStreetMap se embeba.
        $coords = $this->locationCoords();
        $toursData = array_map(function ($data) use ($coords) {
            if (isset($coords[$data['location']])) {
                $data['map_url'] = $coords[$data['location']];
            }
            return $data;
        }, $toursData);

        $tours = collect($toursData)->map(fn ($data) => Tour::query()->updateOrCreate(
            ['title' => $data['title']],
            array_merge($data, ['is_active' => true])
        ));

        // Disponibilidad solo para los primeros tours (suficiente para probar reservas).
        foreach ($tours->take(8) as $tour) {
            for ($i = 1; $i <= 10; $i++) {
                TourAvailability::query()->firstOrCreate(
                    ['tour_id' => $tour->id, 'available_date' => now()->addDays($i)->toDateString()],
                    ['capacity_override' => $tour->max_capacity, 'is_closed' => false]
                );
            }
        }

        return $tours;
    }

    /** Coordenadas "lat,lng" por ubicación (El Salvador) para embeber el mapa. */
    private function locationCoords(): array
    {
        return [
            'Santa Ana'    => '13.8530,-89.6300', // volcán de Santa Ana
            'La Libertad'  => '13.4942,-89.3839', // El Tunco
            'Ahuachapan'   => '13.8706,-89.8500',
            'Ahuachapán'   => '13.8706,-89.8500',
            'Sonsonate'    => '13.7186,-89.7242',
            'Cuscatlán'    => '13.7167,-88.9333',
            'Morazán'      => '13.6969,-88.1006',
            'Chalatenango' => '14.0333,-88.9400',
            'La Paz'       => '13.5000,-88.8686',
            'Usulután'     => '13.3500,-88.4500',
            'San Miguel'   => '13.4833,-88.1833',
            'Cabañas'      => '13.8722,-88.6306',
            'San Vicente'  => '13.6333,-88.8000',
        ];
    }

    /** Genera tours variados (categoría, lugar, precio) con títulos únicos. */
    private function bulkTours(int $count, \Illuminate\Support\Collection $categories): array
    {
        $catNames = $categories->keys()->all() ?: ['Volcanes', 'Playa', 'Cultura', 'Trekking'];
        $places = ['Santa Ana', 'La Libertad', 'Ahuachapán', 'Sonsonate', 'Cuscatlán', 'Morazán', 'Chalatenango', 'La Paz', 'Usulután', 'San Miguel', 'Cabañas', 'San Vicente'];
        $themes = [
            'Volcanes' => ['Ascenso al cráter', 'Mirador volcánico', 'Sendero de lava'],
            'Playa'    => ['Surf al atardecer', 'Snorkel en la bahía', 'Día de playa'],
            'Cultura'  => ['Pueblo colonial', 'Ruta del café', 'Tour de murales'],
            'Trekking' => ['Bosque nuboso', 'Cascadas escondidas', 'Travesía de montaña'],
        ];

        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $cat = $catNames[$i % count($catNames)];
            $place = $places[$i % count($places)];
            $themeList = $themes[$cat] ?? ['Tour de ' . $cat];
            $theme = $themeList[$i % count($themeList)];

            $out[] = [
                'title'         => "{$theme} — {$place} #{$i}",
                'description'   => "Experiencia de {$cat} en {$place}. Guía local, transporte y refrigerio incluidos.",
                'price'         => 30 + (($i % 18) * 5),
                'max_capacity'  => 6 + ($i % 16),
                'location'      => $place,
                'category_id'   => $categories[$cat] ?? null,
                'currency_code' => 'USD',
                'itinerary'     => ['Punto de encuentro', 'Actividad principal', 'Refrigerio', 'Regreso'],
                'highlights'    => ['Guía local', 'Transporte incluido', 'Grupos pequeños'],
                'faqs'          => [['q' => '¿Qué incluye?', 'a' => 'Guía, transporte y refrigerio.']],
            ];
        }

        return $out;
    }

    private function seedVehicles(): \Illuminate\Support\Collection
    {
        $vehiclesData = [
            [
                'title'         => 'SUV 4x4 Adventure',
                'vehicle_type'  => 'suv',
                'description'   => 'Ideal for mountain roads and volcano routes.',
                'location'      => 'San Salvador',
                'hourly_rate'   => 18,
                'daily_rate'    => 110,
                'capacity'      => 6,
                'currency_code' => 'USD',
                'features'      => ['AC', '4x4', 'Insurance'],
            ],
            [
                'title'         => 'Beach Shuttle Van',
                'vehicle_type'  => 'van',
                'description'   => 'Group transfer for beach and city routes.',
                'location'      => 'La Libertad',
                'hourly_rate'   => 22,
                'daily_rate'    => 135,
                'capacity'      => 12,
                'currency_code' => 'USD',
                'features'      => ['AC', 'Driver optional', 'Luggage space'],
            ],
        ];

        // Vehículos adicionales generados para probar paginación / vista móvil.
        $vehiclesData = array_merge($vehiclesData, $this->bulkVehicles(10));

        return collect($vehiclesData)->map(fn ($data) => TransportVehicle::query()->updateOrCreate(
            ['title' => $data['title']],
            array_merge($data, ['is_active' => true])
        ));
    }

    /** Genera vehículos variados con títulos únicos. */
    private function bulkVehicles(int $count): array
    {
        $types = ['suv', 'van', 'bus', 'sedan', 'pickup', 'minibus', 'coaster'];
        $places = ['San Salvador', 'La Libertad', 'Santa Ana', 'Sonsonate', 'San Miguel', 'Ahuachapán'];
        $featureSets = [['AC', 'GPS'], ['AC', '4x4', 'Seguro'], ['AC', 'WiFi', 'Conductor']];

        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $type = $types[$i % count($types)];
            $place = $places[$i % count($places)];

            $out[] = [
                'title'         => ucfirst($type) . " de transporte #{$i}",
                'vehicle_type'  => $type,
                'description'   => "Vehículo tipo {$type} disponible en {$place}.",
                'location'      => $place,
                'hourly_rate'   => 12 + (($i % 10) * 2),
                'daily_rate'    => 80 + (($i % 12) * 10),
                'capacity'      => 4 + ($i % 12),
                'currency_code' => 'USD',
                'features'      => $featureSets[$i % count($featureSets)],
            ];
        }

        return $out;
    }

    private function seedTourBookings(\Illuminate\Support\Collection $tours, \Illuminate\Support\Collection $users): void
    {
        foreach ($tours->take(8)->values() as $index => $tour) {
            $user   = $users[$index % $users->count()];
            $status = $index % 2 === 0 ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING;

            $booking = Booking::query()->firstOrCreate(
                [
                    'bookable_type' => Tour::class,
                    'bookable_id'   => $tour->id,
                    'user_id'       => $user->id,
                    'starts_at'     => now()->addDays($index + 2)->startOfDay(),
                ],
                [
                    'ends_at'       => null,
                    'party_size'    => 2 + $index,
                    'total_price'   => (2 + $index) * (float) $tour->price,
                    'currency_code' => $tour->currency_code ?? 'USD',
                    'status'        => $status,
                ]
            );

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                Invoice::query()->firstOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'amount'        => $booking->total_price,
                        'currency_code' => $booking->currency_code,
                        'status'        => 'issued',
                        'dte_number'    => 'INV-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
                        'issued_at'     => now(),
                    ]
                );
            }
        }
    }

    private function seedTransportBookings(\Illuminate\Support\Collection $vehicles, \Illuminate\Support\Collection $users): void
    {
        foreach ($vehicles->take(4)->values() as $index => $vehicle) {
            $user      = $users[$index % $users->count()];
            // Reservas en el pasado: sirven de historial sin bloquear las fechas por defecto.
            $pickupAt  = now()->subDays(($index + 1) * 3)->setTime(8, 0, 0);
            $dropoffAt = now()->subDays(($index + 1) * 3)->setTime(18, 0, 0);

            $booking = Booking::query()->firstOrCreate(
                [
                    'bookable_type' => TransportVehicle::class,
                    'bookable_id'   => $vehicle->id,
                    'user_id'       => $user->id,
                    'starts_at'     => $pickupAt,
                ],
                [
                    'ends_at'       => $dropoffAt,
                    'party_size'    => 1,
                    'total_price'   => (float) ($vehicle->daily_rate ?? 100),
                    'currency_code' => 'USD',
                    'status'        => Booking::STATUS_CONFIRMED,
                ]
            );

            TransportBookingDetail::query()->firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'pickup_location'  => 'San Salvador Centro',
                    'dropoff_location' => 'Tour Destination',
                    'rental_type'      => 'daily',
                ]
            );

            Invoice::query()->firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'amount'        => $booking->total_price,
                    'currency_code' => $booking->currency_code,
                    'status'        => 'issued',
                    'dte_number'    => 'INV-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
                    'issued_at'     => now(),
                ]
            );

            $booking->payments()->firstOrCreate(
                ['transaction_reference' => 'TRP-' . $booking->id],
                [
                    'gateway'       => 'wompi',
                    'method'        => 'card',
                    'amount'        => $booking->total_price,
                    'currency_code' => $booking->currency_code,
                    'status'        => 'paid',
                    'payload'       => ['seeded' => true],
                    'paid_at'       => now(),
                ]
            );
        }
    }

    private function seedCustomInquiries(\Illuminate\Support\Collection $users): void
    {
        foreach ($users->take(3)->values() as $index => $user) {
            CustomInquiry::query()->firstOrCreate(
                ['user_id' => $user->id, 'message' => 'Custom route request #' . ($index + 1)],
                [
                    'preferred_destinations' => ['Santa Ana', 'La Libertad'],
                    'travel_start_date'      => now()->addWeeks(2)->toDateString(),
                    'travel_end_date'        => now()->addWeeks(2)->addDays(4)->toDateString(),
                    'budget_min'             => 250,
                    'budget_max'             => 650,
                    'travelers_count'        => 2 + $index,
                    'currency_code'          => 'USD',
                    'status'                 => 'pending',
                ]
            );
        }
    }

    private function seedReviews(): void
    {
        $reviewsData = [
            [
                'name'       => 'Carla Morales',
                'location'   => 'San Salvador',
                'tour'       => 'Volcan Santa Ana Sunrise',
                'quote'      => 'Excelente experiencia. El guia fue puntual y el recorrido estuvo muy bien organizado.',
                'rating'     => 5,
                'is_active'  => true,
                'sort_order' => 1,
            ],
            [
                'name'       => 'Javier Rojas',
                'location'   => 'Santa Ana',
                'tour'       => 'Ruta de las Flores Cultural Day',
                'quote'      => 'Recomendado para ir en familia. Buen balance entre cultura, comida y paisajes.',
                'rating'     => 5,
                'is_active'  => true,
                'sort_order' => 2,
            ],
            [
                'name'       => 'Ana Lopez',
                'location'   => 'La Libertad',
                'tour'       => 'El Tunco Surf Session',
                'quote'      => 'Muy buena atencion y clases claras para principiantes. Volveria a reservar.',
                'rating'     => 5,
                'is_active'  => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($reviewsData as $reviewData) {
            Review::query()->updateOrCreate(
                ['name' => $reviewData['name'], 'quote' => $reviewData['quote']],
                $reviewData
            );
        }
    }
}
