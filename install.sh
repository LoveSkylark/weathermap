#!/usr/bin/env bash
# ---------------------------------------------------------------
# Weathermap plugin installer for LibreNMS
# Usage:
#   sudo bash install.sh [LIBRENMS_PATH]
# Default LIBRENMS_PATH is /opt/librenms
# ---------------------------------------------------------------
set -euo pipefail

LIBRENMS="${1:-/opt/librenms}"
PLUGIN_DIR="$LIBRENMS/app/Plugins/Weathermap"
PUBLIC_LINK="$LIBRENMS/public/plugins/Weathermap"

# --- helpers ---
info()  { echo -e "\033[1;34m[INFO]\033[0m  $*"; }
ok()    { echo -e "\033[1;32m[ OK ]\033[0m  $*"; }
die()   { echo -e "\033[1;31m[FAIL]\033[0m  $*" >&2; exit 1; }

# --- 0. preflight ---
[[ -d "$LIBRENMS" ]] || die "LibreNMS directory not found: $LIBRENMS"
command -v php      >/dev/null 2>&1 || die "PHP is not installed or not in PATH"
command -v composer >/dev/null 2>&1 || die "Composer is not installed or not in PATH"

info "LibreNMS path : $LIBRENMS"
info "Plugin path   : $PLUGIN_DIR"

# --- 1. clone or update ---
if [[ -d "$PLUGIN_DIR/.git" ]]; then
    info "Plugin directory already exists — pulling latest changes..."
    git -C "$PLUGIN_DIR" pull --ff-only
    ok "Repository updated"
else
    info "Cloning Weathermap plugin..."
    git clone https://github.com/LoveSkylark/weathermap.git "$PLUGIN_DIR"
    ok "Repository cloned"
fi

# --- 2. Composer dependencies ---
info "Installing Composer dependencies..."
composer install --no-dev --working-dir="$PLUGIN_DIR" --no-interaction
ok "Composer dependencies installed"

# --- 3. Public symlink ---
if [[ -L "$PUBLIC_LINK" ]]; then
    info "Public symlink already exists — skipping"
elif [[ -e "$PUBLIC_LINK" ]]; then
    die "$PUBLIC_LINK exists but is not a symlink — please remove it manually and re-run"
else
    info "Creating public symlink..."
    ln -s "$PLUGIN_DIR/public" "$PUBLIC_LINK"
    ok "Symlink created: $PUBLIC_LINK -> $PLUGIN_DIR/public"
fi

# --- 4. Directories ---
info "Creating output directory (if needed)..."
mkdir -p "$PLUGIN_DIR/public/output"
mkdir -p "$PLUGIN_DIR/configs"

# --- 5. Permissions ---
info "Setting ownership and permissions..."
WEB_USER="www-data"
# Some distros use nginx/apache/caddy — try to detect
for candidate in www-data nginx apache http; do
    if id -u "$candidate" >/dev/null 2>&1; then
        WEB_USER="$candidate"
        break
    fi
done
info "Web server user detected as: $WEB_USER"

chown -R librenms:librenms "$PLUGIN_DIR"
chmod 775 "$PLUGIN_DIR/configs"
chown "$WEB_USER":"$WEB_USER" "$PLUGIN_DIR/public/output"
chmod 775 "$PLUGIN_DIR/public/output"
ok "Permissions set"

# --- 6. Register service provider ---
PROVIDERS_FILE="$LIBRENMS/bootstrap/providers.php"
SP_CLASS="App\\\\Plugins\\\\Weathermap\\\\PluginServiceProvider::class"
SP_LINE="    App\\Plugins\\Weathermap\\PluginServiceProvider::class,"

if [[ -f "$PROVIDERS_FILE" ]]; then
    if grep -q "Weathermap\\\\PluginServiceProvider" "$PROVIDERS_FILE"; then
        info "PluginServiceProvider already registered — skipping"
    else
        info "Registering PluginServiceProvider in bootstrap/providers.php..."
        # Insert before the closing bracket
        sed -i "s|^];|$SP_LINE\n];|" "$PROVIDERS_FILE"
        ok "PluginServiceProvider registered"
    fi
else
    echo ""
    echo "  \033[1;33m[WARN]\033[0m  $PROVIDERS_FILE not found."
    echo "         Add the following line manually to your providers array:"
    echo "         $SP_LINE"
fi

# --- 7. Verify ---
info "Running installation check..."
php "$PLUGIN_DIR/public/check.php" 2>/dev/null | grep -E "OK|FAIL|WARNING|Error" || true

echo ""
echo "---------------------------------------------------------------"
ok "Installation complete!"
echo ""
echo "  Next steps:"
echo "  1. Log in to LibreNMS"
echo "  2. Go to Settings → Plugins and enable 'Weathermap'"
echo "  3. Open the editor at: http://yourserver/plugin/Weathermap/editor"
echo "  4. Add a map config file to: $PLUGIN_DIR/configs/"
echo "  5. Maps are rendered every 5 minutes automatically"
echo "---------------------------------------------------------------"
