<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RockStarUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->users() as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email_verified_at' => now(),
                    'password' => 'password',
                    'bio' => $user['bio'],
                    'slot_coverage' => $user['slot_coverage'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{name: string, email: string, bio: string, slot_coverage: array<int, string>}>
     */
    private function users(): array
    {
        return [
            ['name' => 'James Hetfield', 'email' => 'james@metallica.com', 'bio' => 'Rhythm guitar and vocals.', 'slot_coverage' => ['vocals', 'rhythm_guitar']],
            ['name' => 'Kirk Hammett', 'email' => 'kirk@metallica.com', 'bio' => 'Lead guitar.', 'slot_coverage' => ['lead_guitar']],
            ['name' => 'Robert Trujillo', 'email' => 'robert@metallica.com', 'bio' => 'Bass guitar.', 'slot_coverage' => ['bass']],
            ['name' => 'Lars Ulrich', 'email' => 'lars@metallica.com', 'bio' => 'Drums.', 'slot_coverage' => ['drums']],
            ['name' => 'Dave Grohl', 'email' => 'dave@foo.com', 'bio' => 'Vocals, guitar, and drums.', 'slot_coverage' => ['vocals', 'rhythm_guitar', 'drums']],
            ['name' => 'Taylor Hawkins', 'email' => 'taylor@foo.com', 'bio' => 'Drums and vocals.', 'slot_coverage' => ['drums', 'vocals']],
            ['name' => 'Krist Novoselic', 'email' => 'krist@nirvana.com', 'bio' => 'Bass guitar.', 'slot_coverage' => ['bass']],
            ['name' => 'Kurt Cobain', 'email' => 'kurt@nirvana.com', 'bio' => 'Vocals and guitar.', 'slot_coverage' => ['vocals', 'rhythm_guitar']],
            ['name' => 'Slash', 'email' => 'slash@gnr.com', 'bio' => 'Lead guitar.', 'slot_coverage' => ['lead_guitar']],
            ['name' => 'Duff McKagan', 'email' => 'duff@gnr.com', 'bio' => 'Bass guitar.', 'slot_coverage' => ['bass']],
            ['name' => 'Axl Rose', 'email' => 'axl@gnr.com', 'bio' => 'Lead vocals.', 'slot_coverage' => ['vocals']],
            ['name' => 'Myles Kennedy', 'email' => 'myles@alterbridge.com', 'bio' => 'Vocals and guitar.', 'slot_coverage' => ['vocals', 'rhythm_guitar']],
            ['name' => 'Mark Tremonti', 'email' => 'mark@alterbridge.com', 'bio' => 'Lead guitar and vocals.', 'slot_coverage' => ['lead_guitar', 'vocals']],
            ['name' => 'Flea', 'email' => 'flea@rhcp.com', 'bio' => 'Bass guitar.', 'slot_coverage' => ['bass']],
            ['name' => 'Chad Smith', 'email' => 'chad@rhcp.com', 'bio' => 'Drums.', 'slot_coverage' => ['drums']],
            ['name' => 'John Frusciante', 'email' => 'john@rhcp.com', 'bio' => 'Guitar and vocals.', 'slot_coverage' => ['lead_guitar', 'rhythm_guitar', 'vocals']],
            ['name' => 'Anthony Kiedis', 'email' => 'anthony@rhcp.com', 'bio' => 'Lead vocals.', 'slot_coverage' => ['vocals']],
            ['name' => 'Eddie Vedder', 'email' => 'eddie@pearljam.com', 'bio' => 'Vocals and guitar.', 'slot_coverage' => ['vocals', 'rhythm_guitar']],
            ['name' => 'Mike McCready', 'email' => 'mike@pearljam.com', 'bio' => 'Lead guitar.', 'slot_coverage' => ['lead_guitar']],
            ['name' => 'Jeff Ament', 'email' => 'jeff@pearljam.com', 'bio' => 'Bass guitar.', 'slot_coverage' => ['bass']],
            ['name' => 'Matt Cameron', 'email' => 'matt@pearljam.com', 'bio' => 'Drums.', 'slot_coverage' => ['drums']],
            ['name' => 'Stevie Nicks', 'email' => 'stevie@fleetwoodmac.com', 'bio' => 'Vocals.', 'slot_coverage' => ['vocals']],
            ['name' => 'Lindsey Buckingham', 'email' => 'lindsey@fleetwoodmac.com', 'bio' => 'Guitar and vocals.', 'slot_coverage' => ['lead_guitar', 'rhythm_guitar', 'vocals']],
            ['name' => 'Christine McVie', 'email' => 'christine@fleetwoodmac.com', 'bio' => 'Keys and vocals.', 'slot_coverage' => ['keys', 'vocals']],
            ['name' => 'John Paul Jones', 'email' => 'john@ledzeppelin.com', 'bio' => 'Bass, keys, and arrangements.', 'slot_coverage' => ['bass', 'keys']],
            ['name' => 'Jimmy Page', 'email' => 'jimmy@ledzeppelin.com', 'bio' => 'Lead guitar.', 'slot_coverage' => ['lead_guitar']],
            ['name' => 'Robert Plant', 'email' => 'robert@ledzeppelin.com', 'bio' => 'Lead vocals.', 'slot_coverage' => ['vocals']],
            ['name' => 'John Bonham', 'email' => 'bonham@ledzeppelin.com', 'bio' => 'Drums.', 'slot_coverage' => ['drums']],
            ['name' => 'Elton John', 'email' => 'elton@rocketman.com', 'bio' => 'Keys and vocals.', 'slot_coverage' => ['keys', 'vocals']],
            ['name' => 'Billy Joel', 'email' => 'billy@pianoman.com', 'bio' => 'Keys and vocals.', 'slot_coverage' => ['keys', 'vocals']],
        ];
    }
}
