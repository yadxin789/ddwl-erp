<?php

namespace App\Providers;

use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\System\MediaFile;
use App\Policies\MediaFilePolicy;
use App\Policies\RndProjectDocumentPolicy;
use App\Policies\RndProjectPolicy;
use App\Policies\RndProjectTaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // P0-05 / D-01：研发项目/任务/交付物对象级授权
        Gate::policy(RndProject::class, RndProjectPolicy::class);
        Gate::policy(RndProjectTask::class, RndProjectTaskPolicy::class);
        Gate::policy(RndProjectDocument::class, RndProjectDocumentPolicy::class);

        // P0-07 / B-02：文件下载按业务归属授权
        Gate::policy(MediaFile::class, MediaFilePolicy::class);
    }
}
