<?php

namespace Tests\Unit;

use App\Support\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_text_color_follows_the_background(): void
    {
        $this->assertTrue(Color::wantsLightText('#172B4D'));
        $this->assertTrue(Color::wantsLightText('#A34F2D'));
        $this->assertFalse(Color::wantsLightText('#FAF7F2'));
        $this->assertFalse(Color::wantsLightText('#FFFFFF'));
    }

    public function test_contrast_extremes(): void
    {
        $this->assertEqualsWithDelta(21.0, Color::contrast('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(1.0, Color::contrast('#FFFFFF', '#FFFFFF'), 0.01);
    }

    public function test_mid_tones_cannot_reach_a_readable_contrast(): void
    {
        $this->assertLessThan(4.5, Color::bestContrast('#808080'));
        $this->assertGreaterThan(4.5, Color::bestContrast('#172B4D'));
    }

    public function test_hex_validation(): void
    {
        $this->assertTrue(Color::isHex('#0F3D2E'));
        $this->assertFalse(Color::isHex('0F3D2E'));
        $this->assertFalse(Color::isHex('#FFF'));
        $this->assertFalse(Color::isHex('red; background:url(x)'));
        $this->assertFalse(Color::isHex(null));
    }
}
