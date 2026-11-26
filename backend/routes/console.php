<?php

use App\Jobs\CompleteFinishedBookings;
use App\Jobs\ExpireNoShowBookings;
use App\Jobs\ExpirePendingApprovals;
use App\Jobs\ExpireWaitlistOffers;
use App\Jobs\MarkOverdueLoans;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CompleteFinishedBookings)->everyFiveMinutes();
Schedule::job(new ExpireNoShowBookings)->everyFiveMinutes();
Schedule::job(new ExpirePendingApprovals)->everyFifteenMinutes();
Schedule::job(new ExpireWaitlistOffers)->everyFiveMinutes();
Schedule::job(new MarkOverdueLoans)->hourly();
