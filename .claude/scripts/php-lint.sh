#!/usr/bin/env bash
# PHP syntax check hook for Claude Code
# Reads file_path from CLAUDE_TOOL_INPUT JSON and runs php -l if it's a PHP file

file_path=$(echo "${CLAUDE_TOOL_INPUT:-}" | jq -r '.file_path // empty' 2>/dev/null || true)

if [[ -n "$file_path" && "$file_path" == *.php ]] && [[ -f "$file_path" ]]; then
    php -l "$file_path" 2>&1 || true
fi
