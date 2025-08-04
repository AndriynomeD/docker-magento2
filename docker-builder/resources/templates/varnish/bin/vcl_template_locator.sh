#!/bin/bash

[ "$DEBUG" = "true" ] && set -x

# Function to get Varnish template path for Magento
# Parameters:
# $1 - M2_VERSION (e.g.: 2.4.0, 2.4.*, 2.4.8-p12)
# $2 - VARNISH_VERSION (e.g.: 6.0, 7.1) - only major version is used
# $3 - BASE_PATH (base path to configurations)
get_varnish_template_path() {
    local m2_version="$1"
    local varnish_version="$2"
    local base_path="$3"

    if [[ -z "$m2_version" || -z "$varnish_version" || -z "$base_path" ]]; then
        echo "ERROR: M2_VERSION, VARNISH_VERSION and BASE_PATH are required" >&2
        return 1
    fi

    # Extract major Varnish version and normalize M2 version
    local varnish_major="${varnish_version%%.*}"
    local clean_m2="${m2_version%%-*}"  # Remove patch suffix

    # Normalize version: ensure we have major.minor.patch format (but preserve wildcards)
    if [[ ! "$clean_m2" =~ \* ]]; then
        IFS='.' read -ra parts <<< "$clean_m2"
        clean_m2="${parts[0]}.${parts[1]:-0}.${parts[2]:-0}"
    fi

    # Get available config directories
    local config_dirs=()
    while IFS= read -r -d '' dir; do
        local dirname=$(basename "$dir")
        if [[ "$dirname" =~ ^config[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            config_dirs+=("${dirname#config}")  # Store just the version part
        fi
    done < <(find "$base_path" -maxdepth 1 -type d -name "config[0-9]*.[0-9]*.[0-9]*" -print0 2>/dev/null)

    if [[ ${#config_dirs[@]} -eq 0 ]]; then
        echo "ERROR: No config directories found in $base_path (expected format: config[0-9].[0-9].[0-9])" >&2
        return 1
    fi

    # Function to check if config version matches M2 pattern
    version_matches() {
        local config_ver="$1"
        [[ ! "$clean_m2" =~ \* ]] && [[ "$config_ver" == "$clean_m2" ]] && return 0
        [[ ! "$clean_m2" =~ \* ]] && return 1

        IFS='.' read -ra m2_parts <<< "$clean_m2"
        IFS='.' read -ra config_parts <<< "$config_ver"
        for i in "${!m2_parts[@]}"; do
            [[ "${m2_parts[i]}" == "*" ]] && continue
            [[ "${m2_parts[i]}" == "${config_parts[i]}" ]] || return 1
        done
        return 0
    }

    # Function to calculate version specificity (for sorting)
    version_value() {
        IFS='.' read -ra parts <<< "$1"
        echo $((${parts[0]} * 10000 + ${parts[1]} * 100 + ${parts[2]}))
    }

    # Find best matching version
    local best_version=""
    local best_value=0

    for config_ver in "${config_dirs[@]}"; do
        if version_matches "$config_ver"; then
            local value=$(version_value "$config_ver")
            if [[ $value -gt $best_value ]]; then
                best_version="$config_ver"
                best_value=$value
            fi
        fi
    done

    if [[ -z "$best_version" ]]; then
        echo "ERROR: No matching template found for Magento version $m2_version" >&2
        echo "Available versions: ${config_dirs[*]}" >&2
        return 1
    fi

    # Check if varnish file exists
    local varnish_file="$base_path/config$best_version/varnish${varnish_major}.vcl"
    if [[ ! -f "$varnish_file" ]]; then
        echo "ERROR: Varnish template not found: $varnish_file" >&2
        return 1
    fi

    echo "$varnish_file"
    return 0
}
