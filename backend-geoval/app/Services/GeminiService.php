<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY') ?: config('services.gemini.key');
    }

    /**
     * Generate comments and recommendations using Gemini API.
     */
    public function generateInterpretation(array $ndvi, array $et, string $cropType, int $days, ?array $weather = null): array
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            return $this->generateMockInterpretation($ndvi, $et, $cropType, $days, $weather);
        }

        $prompt = "Vous êtes un expert agronome et spécialiste en gestion de l'eau agricole pour le périmètre irrigué de Bagré.
Analysez les indicateurs suivants pour une parcelle cultivée :
- Type de culture : {$cropType}
- Jours depuis le semis/plantation : {$days} jours
- NDVI Moyen : {$ndvi['ndvi_mean']} (Min : {$ndvi['ndvi_min']}, Max : {$ndvi['ndvi_max']})
- Évapotranspiration de référence (ET0) : {$et['et0']} mm/jour
- Évapotranspiration de la culture (ETc) : {$et['etc']} mm/jour (Kc : {$et['kc']})
";

        if ($weather) {
            $prompt .= "- Conditions Météo : Température {$weather['temperature']}°C, Humidité {$weather['humidity']}%, Précipitations {$weather['precipitation']} mm, Vent {$weather['wind_speed']} m/s, Rayonnement {$weather['solar_radiation']} W/m²\n";
        }

        $prompt .= "\nFournissez une analyse complète rédigée en français sous format JSON avec exactement les deux clés suivantes :
1. \"interpretation\": Un paragraphe de diagnostic sur l'état de vigueur végétative (basé sur le NDVI) et la consommation en eau actuelle de la culture.
2. \"recommendations\": Conseils d'irrigation et actions correctives (ex. augmenter, maintenir ou diminuer l'apport d'eau, surveillance des maladies si anomalie).

Répondez UNIQUEMENT avec le code JSON valide, sans balises markdown (comme ```json) ni texte supplémentaire.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $textOutput = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode(trim($textOutput), true);
                
                if (isset($decoded['interpretation']) && isset($decoded['recommendations'])) {
                    return [
                        'success' => true,
                        'interpretation' => $decoded['interpretation'],
                        'recommendations' => $decoded['recommendations'],
                        'source' => 'gemini-api'
                    ];
                }
            }
            
            Log::error('Gemini API Error Response: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Gemini API call failed: ' . $e->getMessage());
        }

        return $this->generateMockInterpretation($ndvi, $et, $cropType, $days, $weather);
    }

    /**
     * Generate fallback agronomic insights locally.
     */
    private function generateMockInterpretation(array $ndvi, array $et, string $cropType, int $days, ?array $weather = null): array
    {
        $mean = $ndvi['ndvi_mean'];
        $etc = $et['etc'];
        
        // Vigor assessment
        if ($mean < 0.25) {
            $vigor = "très faible ou en phase d'émergence/sol nu";
            $health = "La parcelle présente un couvert végétal très peu développé. Si la culture a été plantée récemment ($days jours), cela est normal. Sinon, cela peut indiquer un échec de levée ou un stress hydrique/phytosanitaire sévère.";
        } elseif ($mean < 0.5) {
            $vigor = "modérée";
            $health = "Le développement végétatif est en cours mais reste moyen. La couverture du sol n'est pas encore optimale. Une attention doit être portée à l'alimentation hydrique pour soutenir la croissance.";
        } elseif ($mean < 0.75) {
            $vigor = "bonne";
            $health = "Bonne vigueur végétative. Les cultures se développent normalement avec un feuillage sain et actif.";
        } else {
            $vigor = "excellente";
            $health = "La vigueur végétative est excellente avec un indice de couverture végétale maximal. La biomasse est très active.";
        }

        // Irrigation advice based on ETc and precipitation
        $precip = $weather['precipitation'] ?? 0.0;
        
        if ($precip >= $etc) {
            $irr = "Les précipitations récentes ({$precip} mm) sont supérieures à la consommation en eau estimée ({$etc} mm/jour). L'irrigation peut être suspendue pour les prochaines 24 à 48 heures afin d'éviter l'asphyxie racinaire.";
        } else {
            $deficit = $etc - $precip;
            $irr = "La consommation en eau de la culture ({$etc} mm/jour) dépasse les apports naturels. Il est recommandé de maintenir un tour d'eau régulier pour combler le déficit de " . round($deficit, 2) . " mm/jour.";
        }

        // Recommendations custom
        $recs = "1. " . $irr . "\n";
        if ($mean < 0.35 && $days > 30) {
            $recs .= "2. Diagnostiquer sur le terrain d'éventuelles attaques de ravageurs ou carences en nutriments (Azote).\n";
            $recs .= "3. Réaliser un apport d'engrais de couverture si les conditions hydriques sont favorables.";
        } else {
            $recs .= "2. Surveiller l'apparition de maladies fongiques favorisées par l'humidité résiduelle sous le couvert dense.\n";
            $recs .= "3. Planifier le prochain tour d'irrigation en fonction de l'évolution de l'ETc.";
        }

        return [
            'success' => true,
            'interpretation' => "L'analyse montre une vigueur végétative {$vigor} (NDVI moyen de {$mean}). {$health}",
            'recommendations' => $recs,
            'source' => 'local-agronomic-engine'
        ];
    }
}
