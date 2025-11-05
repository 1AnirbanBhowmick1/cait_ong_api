# Testing GitHub Actions Workflow

## Quick Test Methods

### 1. Push to GitHub (Simplest)
```bash
# Commit and push your changes
git add .github/workflows/ci-cd.yml
git commit -m "Fix: Create .env file directly instead of copying docker.env"
git push origin main  # or your branch name
```

Then check the GitHub Actions tab:
- Go to: `https://github.com/YOUR_USERNAME/YOUR_REPO/actions`
- Watch the "test" job run
- The "Create environment file" step should now pass

### 2. Test Locally with `act` (GitHub Actions Runner)

Install `act`:
```bash
# Windows (using Chocolatey)
choco install act-cli

# Or download from: https://github.com/nektos/act/releases
```

Run the test job:
```bash
# Test just the test job
act -j test

# Or run a specific workflow
act -W .github/workflows/ci-cd.yml -j test

# With secrets (if needed)
act -j test --secret-file .secrets
```

### 3. Validate YAML Syntax

Check if the workflow file is valid:
```bash
# Using yamllint (if installed)
yamllint .github/workflows/ci-cd.yml

# Or use online validator:
# https://www.yamllint.com/
```

### 4. Manual Step Testing

Test the environment file creation step manually:
```bash
# Create a test script
cat > test-env-creation.sh << 'EOF'
#!/bin/bash
cat > .env << 'ENVEOF'
APP_NAME=CaiTong
APP_ENV=testing
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=testing
DB_USERNAME=test
DB_PASSWORD=password
DB_SCHEMA=public

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=null
ENVEOF

echo "✅ .env file created successfully!"
cat .env
EOF

chmod +x test-env-creation.sh
./test-env-creation.sh
```

### 5. Test Workflow Dispatch (Manual Trigger)

If you add `workflow_dispatch` to your workflow, you can trigger it manually:

```yaml
on:
  push:
    branches: [ main, dev ]
  pull_request:
    branches: [ main, dev ]
  workflow_dispatch:  # Add this
```

Then you can trigger it from GitHub UI:
- Go to Actions tab
- Select "CI/CD Pipeline"
- Click "Run workflow"

## Verification Checklist

After running the workflow, verify:

- [ ] "Create environment file" step completes successfully
- [ ] No "cannot stat 'docker.env'" error
- [ ] `.env` file is created with correct values
- [ ] Subsequent steps (Install Composer, etc.) run successfully
- [ ] Tests pass (if database is configured)

## Troubleshooting

If the workflow still fails:

1. **Check workflow logs** - Look for the exact error message
2. **Verify YAML indentation** - YAML is sensitive to spacing
3. **Test locally first** - Use `act` or manual testing
4. **Check file permissions** - Ensure the workflow can write files

## Quick Fix Verification

The fix replaces:
```yaml
- name: Copy environment file
  run: cp docker.env .env
```

With:
```yaml
- name: Create environment file
  run: |
    cat > .env << EOF
    # ... environment variables ...
    EOF
```

This eliminates the dependency on `docker.env` which is gitignored.

