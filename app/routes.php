<?php

declare(strict_types=1);

use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\RoleMiddleware;
use App\Application\Controllers\FaqController;
use App\Application\Controllers\{
    AuthController,
    SkillController,
    IndustryController,
    JobCategoryController,
    LocationController,
    CompanyController,
    JobController,
    ApplicationController,
    ApplicationNoteController,
    JobSkillController,
    UserSkillController,
    UserExperienceController,
    UserEducationController,
    SavedJobController,
    CompanyReviewController,
    JobAlertController,
    HomeController,
    FindJobController,
    AdminDashboardController,
    RecruiterDashboardController,
    SeekerDashboardController
};

return function (App $app) {
    // CORS OPTIONS
    $app->options('/{routes:.*}', fn($request, $response) => $response);
    $app->get('/', [HomeController::class, 'index']);
    $app->get('/about', \App\Application\Controllers\AboutController::class . ':index');
    $app->get('/faq', FaqController::class . ':index')->setName('faq');
    $app->get('/faqs', FaqController::class . ':index')->setName('faqs');

    // ========== FIND JOB ROUTES ==========
    $app->get('/find-job', [FindJobController::class, 'index'])->setName('find-job');
    $app->get('/find-job/{id}', [FindJobController::class, 'show'])->setName('job-detail');

    // ========== AUTH ==========
    $app->group('', function (Group $group) {
        $group->get('/login', [AuthController::class, 'showLogin']);
        $group->post('/login', [AuthController::class, 'login']);
        $group->get('/register', [AuthController::class, 'showRegister']);
        $group->post('/register', [AuthController::class, 'register']);
        $group->get('/logout', [AuthController::class, 'logout']);
    });

    $app->get('/{role:admin|recruiter|seeker}/applications/show/{id}', [ApplicationController::class, 'show']);
    $app->get('/api/admin/statistics', AdminDashboardController::class . ':getStatistics');
    $app->get('/api/admin/recent-activity', AdminDashboardController::class . ':getRecentActivity');

    // ========== ADMIN ROUTES ==========
    $app->group('/admin', function (Group $group) {
        // Master Data
        $group->get('/skills', [SkillController::class, 'index']);
        $group->get('/skills/create', [SkillController::class, 'create']);
        $group->post('/skills', [SkillController::class, 'store']);
        $group->get('/skills/edit/{id}', [SkillController::class, 'edit']);
        $group->post('/skills/update/{id}', [SkillController::class, 'update']);
        $group->post('/skills/delete/{id}', [SkillController::class, 'delete']);

        $group->get('/industries', [IndustryController::class, 'index']);
        $group->get('/industries/create', [IndustryController::class, 'create']);
        $group->post('/industries', [IndustryController::class, 'store']);
        $group->get('/industries/edit/{id}', [IndustryController::class, 'edit']);
        $group->post('/industries/update/{id}', [IndustryController::class, 'update']);
        $group->post('/industries/delete/{id}', [IndustryController::class, 'delete']);

        // Job Categories - Fixed routing
        $group->get('/job-categories', [JobCategoryController::class, 'index']);
        $group->get('/job-categories/create', [JobCategoryController::class, 'create']);
        $group->post('/job-categories', [JobCategoryController::class, 'store']);
        $group->get('/job-categories/{id}/edit', [JobCategoryController::class, 'edit']);
        $group->post('/job-categories/{id}', [JobCategoryController::class, 'update']);

        $group->get('/locations', [LocationController::class, 'index']);
        $group->get('/locations/data', [LocationController::class, 'getData']);
        $group->get('/locations/create', [LocationController::class, 'create']);
        $group->post('/locations', [LocationController::class, 'store']);
        $group->get('/locations/edit/{id}', [LocationController::class, 'edit']);
        $group->post('/locations/update/{id}', [LocationController::class, 'update']);
        $group->post('/locations/delete/{id}', [LocationController::class, 'delete']);

        // Data Inti
        $group->get('/companies', [CompanyController::class, 'index']);
        $group->get('/companies/data', [CompanyController::class, 'getData']);
        $group->get('/companies/create', [CompanyController::class, 'create']);
        $group->post('/companies', [CompanyController::class, 'store']);
        $group->get('/companies/edit/{id}', [CompanyController::class, 'edit']);
        $group->post('/companies/update/{id}', [CompanyController::class, 'update']);
        $group->post('/companies/delete/{id}', [CompanyController::class, 'delete']);

        $group->get('/jobs', [JobController::class, 'index']);
        $group->get('/jobs/data', [JobController::class, 'getData']);
        $group->get('/jobs/create', [JobController::class, 'create']);
        $group->post('/jobs', [JobController::class, 'store']);
        $group->get('/jobs/edit/{id}', [JobController::class, 'edit']);
        $group->post('/jobs/update/{id}', [JobController::class, 'update']);
        $group->post('/jobs/delete/{id}', [JobController::class, 'delete']);

        // Pelamar dan Relasi
        $group->get('/applications', [ApplicationController::class, 'index']);
        $group->get('/applications/data', [ApplicationController::class, 'getData']);
        $group->get('/applications/show/{id}', [ApplicationController::class, 'show']);
        $group->get('/applications/edit/{id}', [ApplicationController::class, 'edit']);
        $group->post('/applications/update/{id}', [ApplicationController::class, 'update']);
        $group->post('/applications/delete/{id}', [ApplicationController::class, 'delete']);

        $group->get('/application-notes', [ApplicationNoteController::class, 'index']);
        $group->get('/application-notes/create', [ApplicationNoteController::class, 'create']);
        $group->post('/application-notes', [ApplicationNoteController::class, 'store']);
        $group->get('/application-notes/delete/{id}', [ApplicationNoteController::class, 'delete']);

        $group->get('/user-skills', [UserSkillController::class, 'index']);
        $group->get('/user-experiences', [UserExperienceController::class, 'index']);
        $group->get('/user-experiences/data', [UserExperienceController::class, 'getData']);
        $group->get('/user-educations', [UserEducationController::class, 'index']);
        $group->get('/user-educations/data', [UserEducationController::class, 'getData']);
        $group->get('/saved-jobs', [SavedJobController::class, 'index']);
    })->add(new RoleMiddleware('admin'))->add(new AuthMiddleware());

    // ========== RECRUITER ROUTES ==========
    $app->group('/recruiter', function (Group $group) {
        // Profil perusahaan
        $group->get('/profile/edit', [CompanyController::class, 'editOwn']);
        $group->post('/profile/update', [CompanyController::class, 'updateOwn']);

        // Posting lowongan
        $group->get('/jobs', [JobController::class, 'index']);
        $group->get('/jobs/data', [JobController::class, 'getData']);
        $group->get('/jobs/create', [JobController::class, 'create']);
        $group->post('/jobs', [JobController::class, 'store']);
        $group->get('/jobs/edit/{id}', [JobController::class, 'edit']);
        $group->post('/jobs/update/{id}', [JobController::class, 'update']);
        $group->post('/jobs/delete/{id}', [JobController::class, 'delete']);

        // Skill untuk lowongan
        $group->get('/job-skills/{id_job}', [JobSkillController::class, 'index']);
        $group->get('/job-skills/create/{id_job}', [JobSkillController::class, 'create']);
        $group->post('/job-skills', [JobSkillController::class, 'store']);
        $group->get('/job-skills/delete/{id_job}/{id_skill}', [JobSkillController::class, 'delete']);

        // Lihat pelamar & catatan
        $group->get('/applications', [ApplicationController::class, 'index']);
        $group->get('/applications/data', [ApplicationController::class, 'getRecruiterData']);
        $group->delete('/applications/{id}/delete', [ApplicationController::class, 'deleteRecruiter']);

        $group->get('/applications/show/{id}', [ApplicationController::class, 'show']);
        $group->get('/applications/edit/{id}', [ApplicationController::class, 'editRecruiter']); // pastikan ini mengarah ke editRecruiter
        $group->post('/applications/update/{id}', [ApplicationController::class, 'updateRecruiter']); // pastikan ini mengarah ke updateRecruiter

        // Tambah skill jika dibutuhkan
        $group->get('/skills', [SkillController::class, 'index']);
        $group->get('/skills/create', [SkillController::class, 'create']);
        $group->post('/skills', [SkillController::class, 'store']);
        $group->get('/skills/delete/{id}', [SkillController::class, 'delete']);
        $group->post('/skills/delete/{id}', [SkillController::class, 'delete']);
    })->add(new RoleMiddleware('recruiter'))->add(new AuthMiddleware());

    // ========== SEEKER ROUTES ==========
    $app->group('/seeker', function (Group $group) {
        $group->get('/find-job', [\App\Application\Controllers\FindJobController::class, 'seekerIndex'])->setName('seeker.find-job');
        $group->get('/find-job/detail/{id:[0-9]+}', [\App\Application\Controllers\FindJobController::class, 'seekerDetail'])->setName('seeker.job-detail');
        $group->get('/jobs', [JobController::class, 'listAvailable']);
        $group->get('/jobs/data', [\App\Application\Controllers\FindJobController::class, 'getSeekerJobsData']);
        $group->get('/jobs/{id}', [JobController::class, 'show']);
        $group->post('/apply/{id_job}', [ApplicationController::class, 'apply']);
        $group->get('/applications', [ApplicationController::class, 'myApplications']);
        $group->get('/applications/show/{id}', [ApplicationController::class, 'show']);
        $group->get('/applications/create/{id_job:[0-9]+}', [ApplicationController::class, 'createApplicationForm']);
        $group->post('/applications/submit/{id_job:[0-9]+}', [ApplicationController::class, 'submitApplication']);
        $group->get('/applications/data', [ApplicationController::class, 'getSeekerData']);
        $group->post('/applications/delete/{id}', [ApplicationController::class, 'deleteSeeker']);
        $group->get('/saved-jobs', [SavedJobController::class, 'index']);
        $group->post('/saved-jobs/{id_job}', [SavedJobController::class, 'store']);
        $group->get('/saved-jobs/delete/{id_job}', [SavedJobController::class, 'delete']);
        $group->get('/profile', [AuthController::class, 'seekerProfile']);
        $group->post('/profile/update', [AuthController::class, 'updateSeekerProfile']);
        $group->get('/user-skills', [UserSkillController::class, 'index']);
        $group->get('/user-skills/create', [UserSkillController::class, 'create']);
        $group->post('/user-skills', [UserSkillController::class, 'store']);
        $group->get('/user-skills/delete/{id_skill}', [UserSkillController::class, 'delete']);
        $group->get('/user-experiences', [UserExperienceController::class, 'index']);
        $group->get('/user-experiences/create', [UserExperienceController::class, 'create']);
        $group->post('/user-experiences', [UserExperienceController::class, 'store']);
        $group->get('/user-experiences/delete/{id}', [UserExperienceController::class, 'delete']);
        $group->get('/user-educations', [UserEducationController::class, 'index']);
        $group->post('/user-educations', [UserEducationController::class, 'store']);
        $group->get('/user-educations/edit/{id}', [UserEducationController::class, 'edit']);
        $group->post('/user-educations/update/{id}', [UserEducationController::class, 'update']);
        $group->get('/user-educations/delete/{id}', [UserEducationController::class, 'delete']);


    })->add(new RoleMiddleware('seeker'))->add(new AuthMiddleware());

    // ========== DASHBOARDS ==========
    $app->group('/dashboard', function (Group $group) use ($app) {
        $group->get('/admin', fn($req, $res) => $app->getContainer()->get('view')->render($res, 'dashboard/admin.twig'))->add(new RoleMiddleware('admin'));
        $group->get('/recruiter', [RecruiterDashboardController::class, 'index'])->add(new RoleMiddleware('recruiter'));
        $group->get('/seeker', [SeekerDashboardController::class, 'index'])->add(new RoleMiddleware('seeker'));
    })->add(new AuthMiddleware());
};
