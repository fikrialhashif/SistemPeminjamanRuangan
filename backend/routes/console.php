<?php

use App\Jobs\ExpireNoShowBookings;
use App\Jobs\ExpirePendingApprovals;
use App\Jobs\MarkOverdueLoans;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireNoShowBookings)->everyFiveMinutes();
Schedule::job(new ExpirePendingApprovals)->everyFifteenMinutes();
Schedule::job(new MarkOverdueLoans)->hourly();
