<?php
/**
 * Uthenga - Malawi districts and featured city data.
 * Shared mock content for destination guides, planners, and profile sections.
 */

if (!function_exists('uthenga_malawi_districts')) {
    function uthenga_malawi_districts(): array {
        return [
            ['district' => 'Balaka', 'city' => 'Balaka', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1526779259212-939e64788e3c?w=900&fit=crop&q=80', 'summary' => 'A lively trading district with easy access to the lakeshore and southern road links.', 'aliases' => ['Balaka town']],
            ['district' => 'Blantyre', 'city' => 'Blantyre', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=900&fit=crop&q=80', 'summary' => 'Malawi\'s commercial capital and a major gateway for business and nightlife.', 'aliases' => ['Blantyre city', 'Limbe']],
            ['district' => 'Chikwawa', 'city' => 'Chikwawa', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?w=900&fit=crop&q=80', 'summary' => 'Hot lowland landscapes, wildlife access, and a route to Majete Reserve.', 'aliases' => ['Chikwawa town']],
            ['district' => 'Chiradzulu', 'city' => 'Chiradzulu', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=900&fit=crop&q=80', 'summary' => 'Green hills, tea estates, and a calm rural escape close to Blantyre.', 'aliases' => ['Chiradzulu hill']],
            ['district' => 'Chitipa', 'city' => 'Chitipa', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&fit=crop&q=80', 'summary' => 'A border district with mountain scenery and cross-border trade links.', 'aliases' => ['Chitipa town']],
            ['district' => 'Dedza', 'city' => 'Dedza', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=900&fit=crop&q=80', 'summary' => 'Craft pottery, cool highlands, and a classic stop on the road north.', 'aliases' => ['Dedza town']],
            ['district' => 'Dowa', 'city' => 'Dowa', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=900&fit=crop&q=80', 'summary' => 'Rural heartland farming communities and a strong cultural identity.', 'aliases' => ['Dowa boma']],
            ['district' => 'Karonga', 'city' => 'Karonga', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1519608487953-e999c86e7455?w=900&fit=crop&q=80', 'summary' => 'Northern lakeshore hub with museums, heritage sites, and border activity.', 'aliases' => ['Karonga town']],
            ['district' => 'Kasungu', 'city' => 'Kasungu', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1529125512310-8a07d8d8f2da?w=900&fit=crop&q=80', 'summary' => 'A broad district known for wildlife, farming, and long-distance travel stops.', 'aliases' => ['Kasungu town']],
            ['district' => 'Likoma', 'city' => 'Likoma', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=900&fit=crop&q=80', 'summary' => 'Island life on Lake Malawi with beaches, chapels, and boat travel.', 'aliases' => ['Likoma Island']],
            ['district' => 'Lilongwe', 'city' => 'Lilongwe', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=900&fit=crop&q=80', 'summary' => 'The national capital with government offices, markets, and a growing dining scene.', 'aliases' => ['Lilongwe city', 'Area 47', 'Area 10']],
            ['district' => 'Machinga', 'city' => 'Machinga', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1516306539315-797e9d3e4f6b?w=900&fit=crop&q=80', 'summary' => 'A lakeshore-adjacent district with rural tourism potential and road access.', 'aliases' => ['Machinga boma']],
            ['district' => 'Mangochi', 'city' => 'Mangochi / Gosheni City', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=900&fit=crop&q=80', 'summary' => 'Lake Malawi beaches, boat trips, and the Gosheni City hub for travellers.', 'aliases' => ['Mangochi town', 'Gosheni City', 'Lake Malawi']],
            ['district' => 'Mchinji', 'city' => 'Mchinji', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1493246507139-91e8fad9978e?w=900&fit=crop&q=80', 'summary' => 'Border commerce, roadside travel, and access to western Malawi routes.', 'aliases' => ['Mchinji boma']],
            ['district' => 'Mulanje', 'city' => 'Mulanje', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&fit=crop&q=80', 'summary' => 'Home to the dramatic Mulanje Massif and some of Malawi\'s best hiking.', 'aliases' => ['Mulanje mountain']],
            ['district' => 'Mwanza', 'city' => 'Mwanza', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=900&fit=crop&q=80', 'summary' => 'A small but strategic district linking travellers to the southern border corridor.', 'aliases' => ['Mwanza boma']],
            ['district' => 'Mzimba', 'city' => 'Mzimba', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?w=900&fit=crop&q=80', 'summary' => 'Wide rural landscapes, farming, and route access to the north and west.', 'aliases' => ['Mzimba boma']],
            ['district' => 'Neno', 'city' => 'Neno', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=900&fit=crop&q=80', 'summary' => 'A quieter mountain district with scenic roads and a relaxed pace.', 'aliases' => ['Neno boma']],
            ['district' => 'Nkhata Bay', 'city' => 'Nkhata Bay', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1524901548305-08eeddc35080?w=900&fit=crop&q=80', 'summary' => 'Lakefront nightlife, backpacker culture, and boat access along the lake.', 'aliases' => ['Nkhata Bay town']],
            ['district' => 'Nkhotakota', 'city' => 'Nkhotakota', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=900&fit=crop&q=80', 'summary' => 'Historic lakeshore trading routes and access to one of Malawi\'s oldest towns.', 'aliases' => ['Nkhotakota boma']],
            ['district' => 'Nsanje', 'city' => 'Nsanje', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1516939884455-1445c8652f83?w=900&fit=crop&q=80', 'summary' => 'Deep southern plains, river routes, and a strong cross-border transport role.', 'aliases' => ['Nsanje boma']],
            ['district' => 'Ntcheu', 'city' => 'Ntcheu', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=900&fit=crop&q=80', 'summary' => 'A major road-stop district known for farming and roadside services.', 'aliases' => ['Ntcheu boma']],
            ['district' => 'Ntchisi', 'city' => 'Ntchisi', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?w=900&fit=crop&q=80', 'summary' => 'Highland forests, coffee, and green scenery for nature lovers.', 'aliases' => ['Ntchisi forest']],
            ['district' => 'Phalombe', 'city' => 'Phalombe', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=900&fit=crop&q=80', 'summary' => 'Gateway to Mulanje foothills and fertile agricultural land.', 'aliases' => ['Phalombe boma']],
            ['district' => 'Rumphi', 'city' => 'Rumphi', 'region' => 'Northern Region', 'image' => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?w=900&fit=crop&q=80', 'summary' => 'Northern plateau access with wildlife and mountain routes.', 'aliases' => ['Rumphi boma']],
            ['district' => 'Salima', 'city' => 'Salima', 'region' => 'Central Region', 'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=900&fit=crop&q=80', 'summary' => 'Popular lakeshore beaches and resort stays near Lake Malawi.', 'aliases' => ['Senga Bay']],
            ['district' => 'Thyolo', 'city' => 'Thyolo', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?w=900&fit=crop&q=80', 'summary' => 'Tea estates, cool weather, and scenic plantation drives.', 'aliases' => ['Thyolo boma']],
            ['district' => 'Zomba', 'city' => 'Zomba', 'region' => 'Southern Region', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&fit=crop&q=80', 'summary' => 'Historic capital with the plateau, colleges, and a strong student culture.', 'aliases' => ['Zomba plateau', 'Zomba city']],
        ];
    }
}

if (!function_exists('uthenga_malawi_featured_cities')) {
    function uthenga_malawi_featured_cities(): array {
        return [
            ['city' => 'Lilongwe', 'district' => 'Lilongwe', 'image' => 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=900&fit=crop&q=80', 'summary' => 'Capital city energy, markets, and business travel.'],
            ['city' => 'Blantyre', 'district' => 'Blantyre', 'image' => 'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=900&fit=crop&q=80', 'summary' => 'Business, nightlife, and the southern travel hub.'],
            ['city' => 'Zomba', 'district' => 'Zomba', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&fit=crop&q=80', 'summary' => 'Plateau views, heritage, and student culture.'],
            ['city' => 'Mzuzu', 'district' => 'Mzimba', 'image' => 'https://images.unsplash.com/photo-1504893524553-b855a49c4e3d?w=900&fit=crop&q=80', 'summary' => 'Northern gateway city with cool weather and road links.'],
            ['city' => 'Mangochi / Gosheni City', 'district' => 'Mangochi', 'image' => 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=900&fit=crop&q=80', 'summary' => 'Lake Malawi beaches, boats, and holiday stays.'],
        ];
    }
}

if (!function_exists('uthenga_malawi_search_terms')) {
    function uthenga_malawi_search_terms(): array {
        $terms = [];
        foreach (uthenga_malawi_districts() as $row) {
            $terms[] = $row['district'];
            $terms[] = $row['city'];
            foreach (($row['aliases'] ?? []) as $alias) {
                $terms[] = $alias;
            }
        }
        $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));
        usort($terms, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        return $terms;
    }
}

if (!function_exists('uthenga_malawi_find_location')) {
    function uthenga_malawi_find_location(string $query): ?array {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        foreach (uthenga_malawi_districts() as $row) {
            $candidates = array_merge([$row['district'], $row['city']], $row['aliases'] ?? []);
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && stripos($query, $candidate) !== false) {
                    return $row;
                }
            }
        }

        return null;
    }
}
