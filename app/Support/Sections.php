<?php

namespace App\Support;

final class Sections
{
    /** Frontend sections whose background can be changed in Admin > Appearance. */
    public static function all(): array
    {
        return [
            'header' => ['label' => 'Header (navigation bar)', 'default' => '#172B4D'],
            'hero' => ['label' => 'Home: hero', 'default' => '#FAF7F2'],
            'categories' => ['label' => 'Home: categories', 'default' => '#FAF7F2'],
            'featured' => ['label' => 'Home: signature dishes', 'default' => '#FAF7F2'],
            'intro' => ['label' => 'Home: introduction', 'default' => '#FFFFFF'],
            'chef' => ['label' => "Home: chef's recommendations", 'default' => '#FAF7F2'],
            'offers' => ['label' => 'Home: special offers', 'default' => '#172B4D'],
            'gallery' => ['label' => 'Home: gallery', 'default' => '#FAF7F2'],
            'testimonials' => ['label' => 'Home: testimonials', 'default' => '#FFFFFF'],
            'visit' => ['label' => 'Home: hours and location', 'default' => '#FAF7F2'],
            'reserve' => ['label' => 'Home: reservation banner', 'default' => '#A34F2D'],
            'page_header' => ['label' => 'Inner page title bars (About, Menu, ...)', 'default' => '#FFFFFF'],
            'footer' => ['label' => 'Footer', 'default' => '#172B4D'],
        ];
    }
}
