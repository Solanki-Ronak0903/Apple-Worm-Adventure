<?php
namespace Tests\Feature;
use Tests\TestCase;
class SmokeTest extends TestCase {public function test_homepage_is_available():void{$this->get('/')->assertOk()->assertSee('APPLE WORM');}}
