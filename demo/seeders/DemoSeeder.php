<?php

namespace Database\Seeders;

use App\Models\DemoCategory;
use App\Models\DemoCustomer;
use App\Models\DemoTag;
use Illuminate\Database\Seeder;
use Noerd\Models\Tenant;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command?->warn('No tenant found — skipping demo seed data.');

            return;
        }

        if (DemoCategory::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->line('<comment>Demo seed data already exists — skipping.</comment>');

            return;
        }

        // Categories
        $categories = collect([
            'Enterprise',
            'Small Business',
            'Startup',
            'Government',
            'Non-Profit',
        ])->map(fn (string $name) => DemoCategory::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
        ]));

        // Tags
        $tags = collect([
            'VIP',
            'New Lead',
            'Returning',
            'Priority',
            'West Coast',
            'East Coast',
            'Midwest',
            'International',
        ])->map(fn (string $name) => DemoTag::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
        ]));

        // Customers
        $customers = [
            [
                'name' => 'Sarah Johnson',
                'company_name' => 'Pacific Ridge Consulting',
                'email' => 'sarah@pacificridge.com',
                'phone' => '(415) 555-0142',
                'address' => '580 Market St, Suite 400',
                'zipcode' => '94104',
                'city' => 'San Francisco',
                'description' => 'Management consulting firm specializing in digital transformation for mid-size companies.',
                'status' => 'completed',
                'priority' => 'high',
                'revenue' => 245000.00,
                'brand_color' => '#2563EB',
                'is_active' => true,
                'contract_start' => '2024-03-15',
                'preferred_time' => '09:00',
                'category' => 'Enterprise',
                'tags' => ['VIP', 'West Coast'],
            ],
            [
                'name' => 'Michael Chen',
                'company_name' => 'Brightpath Analytics',
                'email' => 'mchen@brightpath.io',
                'phone' => '(212) 555-0198',
                'address' => '350 Fifth Ave, 21st Floor',
                'zipcode' => '10118',
                'city' => 'New York',
                'description' => 'Data analytics platform for e-commerce businesses.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'revenue' => 89500.00,
                'brand_color' => '#059669',
                'is_active' => true,
                'contract_start' => '2025-01-10',
                'preferred_time' => '10:30',
                'category' => 'Startup',
                'tags' => ['New Lead', 'East Coast'],
            ],
            [
                'name' => 'Emily Rodriguez',
                'company_name' => 'Cornerstone Legal Group',
                'email' => 'erodriguez@cornerstonelegal.com',
                'phone' => '(312) 555-0167',
                'address' => '233 S Wacker Dr, Suite 8400',
                'zipcode' => '60606',
                'city' => 'Chicago',
                'description' => 'Full-service law firm focused on corporate and intellectual property law.',
                'status' => 'completed',
                'priority' => 'high',
                'revenue' => 178000.00,
                'brand_color' => '#7C3AED',
                'is_active' => true,
                'contract_start' => '2024-08-01',
                'preferred_time' => '14:00',
                'category' => 'Enterprise',
                'tags' => ['VIP', 'Returning', 'Midwest'],
            ],
            [
                'name' => 'David Park',
                'company_name' => 'Summit Hardware Co.',
                'email' => 'david@summithardware.com',
                'phone' => '(503) 555-0134',
                'address' => '1120 NW Couch St',
                'zipcode' => '97209',
                'city' => 'Portland',
                'description' => 'Regional hardware store chain with 12 locations across Oregon and Washington.',
                'status' => 'new',
                'priority' => 'low',
                'revenue' => 42000.00,
                'brand_color' => '#D97706',
                'is_active' => true,
                'contract_start' => '2025-11-01',
                'preferred_time' => '08:00',
                'category' => 'Small Business',
                'tags' => ['New Lead', 'West Coast'],
            ],
            [
                'name' => 'Amanda Foster',
                'company_name' => 'GreenLeaf Foundation',
                'email' => 'afoster@greenleaf.org',
                'phone' => '(202) 555-0189',
                'address' => '1625 K St NW',
                'zipcode' => '20006',
                'city' => 'Washington',
                'description' => 'Environmental non-profit focused on urban reforestation and community gardens.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'revenue' => 15000.00,
                'brand_color' => '#16A34A',
                'is_active' => true,
                'contract_start' => '2025-06-01',
                'preferred_time' => '11:00',
                'category' => 'Non-Profit',
                'tags' => ['Priority', 'East Coast'],
            ],
            [
                'name' => 'James Wilson',
                'company_name' => 'Apex Manufacturing',
                'email' => 'jwilson@apexmfg.com',
                'phone' => '(713) 555-0156',
                'address' => '4200 Westheimer Rd',
                'zipcode' => '77027',
                'city' => 'Houston',
                'description' => 'Precision manufacturing of aerospace components and industrial equipment.',
                'status' => 'completed',
                'priority' => 'critical',
                'revenue' => 520000.00,
                'brand_color' => '#DC2626',
                'is_active' => true,
                'contract_start' => '2023-11-20',
                'preferred_time' => '07:30',
                'category' => 'Enterprise',
                'tags' => ['VIP', 'Returning', 'Priority'],
            ],
            [
                'name' => 'Lisa Thompson',
                'company_name' => 'Crestview Schools District',
                'email' => 'lthompson@crestviewsd.gov',
                'phone' => '(602) 555-0145',
                'address' => '3300 N Central Ave',
                'zipcode' => '85012',
                'city' => 'Phoenix',
                'description' => 'Public school district serving 45,000 students across the greater Phoenix area.',
                'status' => 'new',
                'priority' => 'medium',
                'revenue' => 67000.00,
                'brand_color' => '#0891B2',
                'is_active' => false,
                'contract_start' => null,
                'preferred_time' => '13:00',
                'category' => 'Government',
                'tags' => ['New Lead'],
            ],
            [
                'name' => 'Robert Martinez',
                'company_name' => 'Velocity Fitness Studios',
                'email' => 'rob@velocityfitness.com',
                'phone' => '(305) 555-0173',
                'address' => '801 Brickell Ave',
                'zipcode' => '33131',
                'city' => 'Miami',
                'description' => 'Boutique fitness chain with locations in South Florida.',
                'status' => 'cancelled',
                'priority' => 'low',
                'revenue' => 28000.00,
                'brand_color' => '#F59E0B',
                'is_active' => false,
                'contract_start' => '2024-05-01',
                'preferred_time' => '16:00',
                'category' => 'Small Business',
                'tags' => ['East Coast'],
            ],
        ];

        $categoryMap = $categories->keyBy('name');
        $tagMap = $tags->keyBy('name');

        foreach ($customers as $data) {
            $categoryName = $data['category'];
            $tagNames = $data['tags'];
            unset($data['category'], $data['tags']);

            $customer = DemoCustomer::create(array_merge($data, [
                'tenant_id' => $tenant->id,
                'demo_category_id' => $categoryMap[$categoryName]->id,
            ]));

            $customer->tags()->attach(
                collect($tagNames)->map(fn (string $name) => $tagMap[$name]->id),
            );
        }

        $this->command?->info('Demo seed data created: 8 customers, 5 categories, 8 tags.');
    }
}
