<?php

namespace OiLab\OiLaravelDrive\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * @extends Factory<Drive>
 */
class DriveFactory extends Factory
{
    protected $model = Drive::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
        ];
    }

    /**
     * The drive of this entity.
     */
    public function ownedBy(Model $driveable): static
    {
        return $this->state([
            'driveable_type' => $driveable->getMorphClass(),
            'driveable_id' => $driveable->getKey(),
        ]);
    }
}
