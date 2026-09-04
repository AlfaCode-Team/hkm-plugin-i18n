<?php

declare(strict_types=1);

/**
 * English validation messages for Plugins\Validation.
 *
 * Keys are RULE NAMES. The Validator looks up `validation.{rule}` before
 * falling back to its own built-in default, so anything listed here overrides
 * the plugin's hardcoded copy — and anything NOT listed here silently falls
 * back to English even in another locale. Keep this file and lang/fr in step;
 * CatalogueParityTest enforces that they have identical keys.
 *
 * Covered: the Validator's built-in rules, plus the CommonRules and
 * FinancialRules packs (`Validator::extendWith(new CommonRules())`).
 *
 * :field is the field name, resolved through `attributes` below and humanised
 * when absent. Rule parameters arrive as :min, :max, :other, :values, :param.
 */
return [
    // ── Presence and type ────────────────────────────────────────────────────
    'required'  => 'The :field field is required.',
    'string'    => 'The :field field must be a string.',
    'integer'   => 'The :field field must be an integer.',
    'numeric'   => 'The :field field must be numeric.',
    'boolean'   => 'The :field field must be true or false.',
    'array'     => 'The :field field must be an array.',
    'list'      => 'The :field field must be a list.',
    'accepted'  => 'The :field field must be accepted.',
    'declined'  => 'The :field field must be declined.',

    // ── Formats ──────────────────────────────────────────────────────────────
    'email'     => 'The :field field must be a valid email address.',
    'url'       => 'The :field field must be a valid URL.',
    'http_url'  => 'The :field field must be a valid http(s) URL.',
    'domain'    => 'The :field field must be a valid domain.',
    'timezone'  => 'The :field field must be a valid timezone.',
    'locale'    => 'The :field field must be a locale like en_US.',
    'currency'  => 'The :field field must be a 3-letter ISO 4217 code.',
    'phone'     => 'The :field field must be 7–15 digits (optional leading +).',
    'e164'      => 'The :field field must be a valid E.164 phone number.',
    'uuid'      => 'The :field field must be a valid UUID.',
    'ulid'      => 'The :field field must be a valid ULID.',
    'slug'      => 'The :field field must be a lowercase kebab-case slug.',
    'username'  => 'The :field field must be 3+ letters, numbers or underscores.',
    'json'      => 'The :field field must be valid JSON.',
    'base64'    => 'The :field field must be valid base64.',
    'hex_color' => 'The :field field must be a valid hex colour.',
    'ip'        => 'The :field field must be a valid IP address.',
    'ipv4'      => 'The :field field must be a valid IPv4 address.',
    'ipv6'      => 'The :field field must be a valid IPv6 address.',
    'mac_address' => 'The :field field must be a valid MAC address.',

    // ── Character classes ────────────────────────────────────────────────────
    'alpha'               => 'The :field field may only contain letters.',
    'alpha_num'           => 'The :field field may only contain letters and numbers.',
    'alpha_dash'          => 'The :field field may only contain letters, numbers, dashes and underscores.',
    'alpha_space'         => 'The :field field may only contain letters and spaces.',
    'alpha_numeric_punct' => 'The :field field contains an invalid character.',
    'ascii'               => 'The :field field may only contain ASCII characters.',
    'lowercase'           => 'The :field field must be lowercase.',
    'uppercase'           => 'The :field field must be uppercase.',
    'hex'                 => 'The :field field must be hexadecimal.',

    // ── Numbers ──────────────────────────────────────────────────────────────
    'min'                => 'The :field field must be at least :min.',
    'max'                => 'The :field field must not be greater than :max.',
    'between'            => 'The :field field must be between :min and :max.',
    'size'               => 'The :field field must be of size :param.',
    'gt'                 => 'The :field field must be greater than :param.',
    'gte'                => 'The :field field must be at least :param.',
    'lt'                 => 'The :field field must be less than :param.',
    'lte'                => 'The :field field must not be greater than :param.',
    'digits'             => 'The :field field must be all digits.',
    'digits_between'     => 'The :field field has an invalid number of digits.',
    'min_digits'         => 'The :field field must have at least :param digits.',
    'max_digits'         => 'The :field field must not exceed :param digits.',
    'decimal'            => 'The :field field must be a decimal number.',
    'multiple_of'        => 'The :field field must be a multiple of :param.',
    'is_natural'         => 'The :field field must be a non-negative whole number.',
    'is_natural_no_zero' => 'The :field field must be a positive whole number.',

    // ── Dates ────────────────────────────────────────────────────────────────
    'date'        => 'The :field field must be a valid date.',
    'date_format' => 'The :field field does not match the required format.',
    'before'      => 'The :field field must be a date before :param.',
    'after'       => 'The :field field must be a date after :param.',

    // ── Comparison and membership ────────────────────────────────────────────
    'in'                => 'The selected :field is invalid.',
    'not_in'            => 'The selected :field is invalid.',
    'enum'              => 'The selected :field is invalid.',
    'distinct'          => 'The :field field has duplicate values.',
    'regex'             => 'The :field field format is invalid.',
    'same'              => 'The :field field must match :other.',
    'different'         => 'The :field field must be different from :other.',
    'confirmed'         => 'The :field field confirmation does not match.',
    'starts_with'       => 'The :field field has an invalid prefix.',
    'ends_with'         => 'The :field field has an invalid suffix.',
    'doesnt_start_with' => 'The :field field has a forbidden prefix.',
    'doesnt_end_with'   => 'The :field field has a forbidden suffix.',

    // ── FinancialRules pack ──────────────────────────────────────────────────
    'luhn'        => 'The :field field failed its checksum.',
    'credit_card' => 'The :field field must be a valid card number.',
    'cvv'         => 'The :field field must be a 3 or 4 digit security code.',
    'iban'        => 'The :field field must be a valid IBAN.',
    'bic'         => 'The :field field must be a valid BIC/SWIFT code.',

    /*
     * ── Field names ─────────────────────────────────────────────────────────
     * What :field is replaced with. Input keys are WIRE names — without this,
     * a reader is told "The firstName field is required."
     *
     * A key absent here is humanised instead (firstName / first_name →
     * "first name"), which is why this list only needs to carry the names that
     * humanising gets wrong or that deserve better prose. Applications add
     * their own by overriding `validation.attributes` from their project
     * catalogue — lang GROUPS MERGE across the cascade, so a project lists only
     * the fields it adds, not the whole file.
     */
    'attributes' => [
        'email'                 => 'email',
        'password'              => 'password',
        'password_confirmation' => 'password confirmation',
        'passwordConfirmation'  => 'password confirmation',
        'url'                   => 'URL',
        'uri'                   => 'URI',
        'ip'                    => 'IP address',
        'dob'                   => 'date of birth',
        'tos'                   => 'terms of service',
        'id'                    => 'ID',
        'uuid'                  => 'UUID',
        'sku'                   => 'SKU',
        'vat'                   => 'VAT number',
        'iban'                  => 'IBAN',
        'bic'                   => 'BIC/SWIFT code',
        'cvv'                   => 'security code',
    ],
];
