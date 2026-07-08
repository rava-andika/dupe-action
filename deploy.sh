#!/bin/bash

# This script safely updates the deployment from the 'build' branch.

# Step 1: Fetch the latest state from the remote repository (GitHub).
# This downloads all the new information without trying to change any files yet.
echo "Fetching latest changes from GitHub..."
git fetch origin

# Step 2: Force the local 'build' branch to match the remote 'build' branch exactly.
# The --hard flag discards any local history and makes it a perfect mirror.
echo "Resetting local branch to match remote 'build' branch..."
git reset --hard origin/build

# Step 3: Clear the previous optimizations.
echo "Clearing the previous optimizations..."
php artisan optimize:clear

# Step 4: Restart workers to ensure they are up to date.
echo "Restarting workers..."
php artisan queue:restart

# Step 5: Optimize the build files.
echo "Optimize the build files..."
php artisan optimize

echo "Deployment complete. Your site is now up to date."