#!/usr/bin/env bash
# Health check for Filament LMS verification environment

set -euo pipefail

echo "🏥 Filament LMS Environment Health Check"
echo "========================================"
echo ""

PASSED=0
FAILED=0

# Check 1: Server responding
echo -n "1. Server responding (http://localhost:8000/lms)... "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/lms | grep -q "200\|302"; then
    echo "✓ PASS"
    ((PASSED++))
else
    echo "✗ FAIL (server not responding)"
    ((FAILED++))
fi

# Check 2: Database has courses
echo -n "2. Database has courses... "
COURSE_COUNT=$(php vendor/bin/testbench tinker --execute="echo \Tapp\FilamentLms\Models\Course::count();" 2>/dev/null | tail -1)
if [[ "$COURSE_COUNT" -gt 0 ]]; then
    echo "✓ PASS ($COURSE_COUNT courses)"
    ((PASSED++))
else
    echo "✗ FAIL (no courses found)"
    ((FAILED++))
fi

# Check 3: Test user exists
echo -n "3. Test user exists (admin@example.com)... "
USER_EXISTS=$(php vendor/bin/testbench tinker --execute="echo \Tapp\FilamentLms\Tests\TestUser::where('email', 'admin@example.com')->exists() ? 'yes' : 'no';" 2>/dev/null | tail -1)
if [[ "$USER_EXISTS" == "yes" ]]; then
    echo "✓ PASS"
    ((PASSED++))
else
    echo "✗ FAIL (user not found)"
    ((FAILED++))
fi

# Check 4: Port 8000 owned by testbench
echo -n "4. Port 8000 owned by testbench... "
if lsof -ti:8000 >/dev/null 2>&1; then
    PROCESS=$(lsof -ti:8000 | xargs -I {} ps -p {} -o comm= 2>/dev/null || echo "unknown")
    if echo "$PROCESS" | grep -q "php"; then
        echo "✓ PASS (owned by $PROCESS)"
        ((PASSED++))
    else
        echo "⚠ WARNING (owned by $PROCESS, not PHP)"
        ((FAILED++))
    fi
else
    echo "✗ FAIL (port not in use)"
    ((FAILED++))
fi

echo ""
echo "========================================"
echo "Results: $PASSED passed, $FAILED failed"
echo ""

if [[ $FAILED -eq 0 ]]; then
    echo "✅ Environment is healthy!"
    exit 0
else
    echo "⚠ Some checks failed. Run seed script or restart server."
    exit 1
fi
