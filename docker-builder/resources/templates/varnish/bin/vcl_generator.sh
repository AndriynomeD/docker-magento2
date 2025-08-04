#!/bin/bash

[ "$DEBUG" = "true" ] && set -x

source /usr/local/bin/vcl_template_locator.sh

render_template() {
    local template_file="$1"
    local output_file="$2"
    local -n template_vars=$3

    cp "$template_file" "$output_file"
    for var_name in "${!template_vars[@]}"; do
        local var_value="${template_vars[$var_name]}"
        if [[ "$var_value" == *$'\n'* ]]; then
            # Use perl for multiline replacement to avoid sed issues
            perl -i -pe "s|/\\*\\s*\\{\\{\\s*$var_name\\s*\\}\\}\\s*\\*/|$var_value|g" "$output_file"
        else
            var_value=$(printf '%s\n' "$var_value" | sed 's/[\[\].*^$()+{}|]/\\&/g')
            sed -i -e "s|/\*[[:space:]]*{{[[:space:]]*$var_name[[:space:]]*}}[[:space:]]*\*/|$var_value|g" "$output_file"
        fi
    done

    local unresolved=$(grep -o '{{[^}]*}}' "$output_file" || true)
    if [ -n "$unresolved" ]; then
        echo "ERROR: Unresolved template variables found: $unresolved" >&2
        return 1
    fi

    return 0
}

getTransformedAccessList() {
    local ip_list="$1"
    local result=""

    IFS=',' read -ra IPS <<< "$ip_list"
    for ip in "${IPS[@]}"; do
        ip=$(echo "$ip" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
        if [ -n "$ip" ]; then
            result="${result}    \"${ip}\";"$'\n'
        fi
    done

    # Remove the last newline
    result=$(echo -n "$result" | sed '$s/$//')
    echo "$result"
}

getReplacements() {
    local ssl_header="$1"
    local backend_host="$2"
    local backend_port="$3"
    local purge_ips="$4"
    local design_exceptions="$5"
    local grace_period="$6"

    declare -A replacements=(
        ["ssl_offloaded_header"]="$ssl_header"
        ["host"]="$backend_host"
        ["port"]="$backend_port"
        ["ips"]="$(getTransformedAccessList "$purge_ips")"
        ["design_exceptions_code"]="$design_exceptions"
        ["grace_period"]="$grace_period"
    )

    declare -p replacements | sed 's/declare -A replacements=//'
}

generateVcl() {
    local magento_edition="$1"
    local magento_version="$2"
    local varnish_version="$3"
    local output_file="$4"
    local -n local_vcl_vars=$5

    # Check if custom VCL config path is provided
    if [ -n "$VCL_CONFIG_PATH" ]; then
        echo "Using custom VCL config: $VCL_CONFIG_PATH"

        # Validate custom config exists and is readable
        if [ ! -r "$VCL_CONFIG_PATH" ]; then
            echo "ERROR: Custom VCL config not found or not readable: $VCL_CONFIG_PATH" >&2
            return 1
        fi

        local template_path="$VCL_CONFIG_PATH"
    else
        # Use template resolution logic
        local base_config_path="/etc/varnish/config/configM2"
        if [ "$magento_edition" = "mage-os" ]; then
            base_config_path="/etc/varnish/config/configMageOs"
        fi

        local template_path=$(get_varnish_template_path "$magento_version" "$varnish_version" "$base_config_path")
        if [ $? -ne 0 ]; then
            echo "ERROR: VCL config not found for version $magento_version/$varnish_version" >&2
            return 1
        fi

        echo "Using VCL template: $template_path"
    fi

    # Render template
    if render_template "$template_path" "$output_file" local_vcl_vars; then
        echo "Template rendered successfully"
        return 0
    else
        echo "Template rendering failed" >&2
        return 1
    fi
}
