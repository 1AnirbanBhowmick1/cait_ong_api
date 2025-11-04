# Quick Start - Parallel Processing

## Current Status
- ✅ 50 companies already loaded
- ⏳ 112 jobs in queue (ready to process)

## Process All Jobs (Fast!)

### Option 1: Process All Jobs Now (Recommended)
```bash
# Process all jobs in queue (will take ~15-20 minutes for 112 jobs)
php artisan queue:work --queue=default --tries=3 --timeout=60 --stop-when-empty
```

### Option 2: Process in Background
```bash
# Run in background
nohup php artisan queue:work --queue=default --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Check progress
tail -f storage/logs/queue-worker.log
```

### Option 3: Process with Concurrency (Faster)
If you want to process multiple jobs simultaneously:
```bash
# Process 10 jobs at once (much faster!)
php artisan queue:work --queue=default --tries=3 --timeout=60 --max-jobs=1000
```

## Monitor Progress

While jobs process, check progress:
```bash
# Count loaded companies
php artisan tinker --execute="echo App\Models\Company::where('extraction_flag', true)->count();"

# Check remaining jobs
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

## Load More Companies

To load ALL oil & gas companies (not just 100):
```bash
# Load all (will dispatch jobs for all companies)
php artisan companies:load-from-sec --oil-gas-only --queue --resume
```

Then start queue worker to process them all.

