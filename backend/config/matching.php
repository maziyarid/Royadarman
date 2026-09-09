<?php

return [
    'default_strategy' => env('MATCHING_STRATEGY', 'scoring'),

    'strategies' => [
        'scoring' => [
            'enabled' => true,
            'weights' => [
                'distance' => (float) env('MATCHING_DISTANCE_WEIGHT', 0.3),
                'budget' => (float) env('MATCHING_BUDGET_WEIGHT', 0.25),
                'specialty' => (float) env('MATCHING_SPECIALTY_WEIGHT', 0.2),
                'availability' => (float) env('MATCHING_AVAILABILITY_WEIGHT', 0.15),
                'rating' => (float) env('MATCHING_RATING_WEIGHT', 0.1),
            ],
            'max_candidates' => (int) env('MATCHING_MAX_CANDIDATES', 10),
            'min_score' => (float) env('MATCHING_MIN_SCORE', 0.5),
        ],

        'instant' => [
            'enabled' => (bool) env('MATCHING_INSTANT_ENABLED', true),
            'max_distance_km' => (float) env('MATCHING_MAX_DISTANCE_KM', 50),
            'max_results' => (int) env('MATCHING_INSTANT_MAX_RESULTS', 5),
        ],

        'manual' => [
            'enabled' => (bool) env('MATCHING_MANUAL_ENABLED', true),
            'require_coordinator_approval' => (bool) env('MATCHING_MANUAL_REQUIRE_APPROVAL', false),
        ],
    ],

    'scoring' => [
        'distance' => [
            'max_km' => (float) env('MATCHING_DISTANCE_MAX_KM', 100),
            'decay_function' => env('MATCHING_DISTANCE_DECAY', 'exponential'),
        ],

        'budget' => [
            'bands' => [
                'economy' => ['min' => 0, 'max' => 5000000, 'score' => 0.8],
                'standard' => ['min' => 5000000, 'max' => 15000000, 'score' => 1.0],
                'premium' => ['min' => 15000000, 'max' => 50000000, 'score' => 1.2],
                'luxury' => ['min' => 50000000, 'max' => null, 'score' => 1.5],
            ],
        ],

        'specialty' => [
            'exact_match_bonus' => (float) env('MATCHING_SPECIALTY_EXACT_BONUS', 1.0),
            'category_match_bonus' => (float) env('MATCHING_SPECIALTY_CATEGORY_BONUS', 0.5),
        ],

        'availability' => [
            'immediate_bonus' => (float) env('MATCHING_AVAILABILITY_IMMEDIATE_BONUS', 1.0),
            'within_24h_bonus' => (float) env('MATCHING_AVAILABILITY_24H_BONUS', 0.7),
            'within_48h_bonus' => (float) env('MATCHING_AVAILABILITY_48H_BONUS', 0.5),
        ],

        'rating' => [
            'min_reviews' => (int) env('MATCHING_RATING_MIN_REVIEWS', 5),
            'weight_by_reviews' => (bool) env('MATCHING_RATING_WEIGHT_BY_REVIEWS', true),
        ],
    ],

    'caching' => [
        'enabled' => (bool) env('MATCHING_CACHE_ENABLED', true),
        'ttl_minutes' => (int) env('MATCHING_CACHE_TTL_MINUTES', 30),
    ],

    'geospatial' => [
        'provider' => env('GEOSPATIAL_PROVIDER', 'database'),
        'tehran_center' => [
            'latitude' => (float) env('TEHRAN_CENTER_LAT', 35.6892),
            'longitude' => (float) env('TEHRAN_CENTER_LON', 51.3890),
        ],
        'distance_calculation' => env('DISTANCE_CALCULATION', 'haversine'),
    ],

    'preferences' => [
        'gender_preference_weight' => (float) env('MATCHING_GENDER_PREFERENCE_WEIGHT', 0.15),
        'language_preference_weight' => (float) env('MATCHING_LANGUAGE_PREFERENCE_WEIGHT', 0.1),
        'time_preference_weight' => (float) env('MATCHING_TIME_PREFERENCE_WEIGHT', 0.05),
    ],
];
