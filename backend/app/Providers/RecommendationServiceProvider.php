<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Services\Recommendation\CandidateGeneratorService;
use App\Services\Recommendation\Engines\AiRecommendationEngine;
use App\Services\Recommendation\Engines\RuleBasedRecommendationEngine;
use App\Services\Recommendation\Pipeline\DiversityStage;
use App\Services\Recommendation\Pipeline\ExplorationStage;
use App\Services\Recommendation\Pipeline\FilteringStage;
use App\Services\Recommendation\Pipeline\FinalRankingStage;
use App\Services\Recommendation\Pipeline\ScoringStage;
use App\Services\Recommendation\Sources\BookmarksCandidateSource;
use App\Services\Recommendation\Sources\CategoryCandidateSource;
use App\Services\Recommendation\Sources\ExplorationCandidateSource;
use App\Services\Recommendation\Sources\LiveCandidateSource;
use App\Services\Recommendation\Sources\NewCandidateSource;
use App\Services\Recommendation\Sources\PopularCandidateSource;
use App\Services\Recommendation\Sources\PreviouslyWatchedCandidateSource;
use App\Services\Recommendation\Sources\SellerCandidateSource;
use App\Services\Recommendation\Sources\TrendingCandidateSource;
use Illuminate\Support\ServiceProvider;

class RecommendationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerCandidateSources();
        $this->registerEngine();
        $this->registerCandidateGenerator();
    }

    private function registerCandidateSources(): void
    {
        $sources = [
            TrendingCandidateSource::class,
            PopularCandidateSource::class,
            NewCandidateSource::class,
            BookmarksCandidateSource::class,
            ExplorationCandidateSource::class,
            PreviouslyWatchedCandidateSource::class,
            CategoryCandidateSource::class,
            SellerCandidateSource::class,
            LiveCandidateSource::class,
        ];

        $this->app->tag($sources, 'recommendation.candidate_source');
    }

    private function registerEngine(): void
    {
        $this->app->bind(RecommendationEngineInterface::class, function ($app): RecommendationEngineInterface {
            $engine = (string) config('recommendation.engine', 'rule');

            if ($engine === 'ai') {
                return $app->make(AiRecommendationEngine::class);
            }

            return $app->make(RuleBasedRecommendationEngine::class);
        });

        $this->app->when(RuleBasedRecommendationEngine::class)
            ->needs('$stages')
            ->give(static fn ($app): array => [
                $app->make(FilteringStage::class),
                $app->make(ScoringStage::class),
                $app->make(DiversityStage::class),
                $app->make(ExplorationStage::class),
                $app->make(FinalRankingStage::class),
            ]);
    }

    private function registerCandidateGenerator(): void
    {
        $this->app->singleton(CandidateGeneratorService::class, function ($app): CandidateGeneratorService {
            /** @var list<CandidateSourceInterface> $resolved */
            $resolved = iterator_to_array($app->tagged('recommendation.candidate_source'));

            return new CandidateGeneratorService(collect($resolved));
        });
    }
}
