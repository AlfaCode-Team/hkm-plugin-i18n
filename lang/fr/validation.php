<?php

declare(strict_types=1);

/**
 * French validation messages for Plugins\Validation.
 *
 * Keys mirror lang/en/validation.php exactly — CatalogueParityTest enforces
 * that, because a key missing here does not fail, it silently serves English
 * inside an otherwise French page, which is the harder bug to notice.
 *
 * A note on wording: French normally takes a definite article before a field
 * name (« le champ email »), but the article is gendered and :field is a runtime
 * value, so there is no way to pick « le » or « la » correctly here. These
 * messages use « Le champ :field » throughout — grammatically it treats "champ"
 * (masculine) as the head noun, which stays correct whatever :field contains.
 * Do not "fix" this to « La :field » for feminine field names; the substitution
 * has no gender information to work from.
 */
return [
    // ── Présence et type ─────────────────────────────────────────────────────
    'required'  => 'Le champ :field est obligatoire.',
    'string'    => 'Le champ :field doit être une chaîne de caractères.',
    'integer'   => 'Le champ :field doit être un nombre entier.',
    'numeric'   => 'Le champ :field doit être numérique.',
    'boolean'   => 'Le champ :field doit être vrai ou faux.',
    'array'     => 'Le champ :field doit être un tableau.',
    'list'      => 'Le champ :field doit être une liste.',
    'accepted'  => 'Le champ :field doit être accepté.',
    'declined'  => 'Le champ :field doit être refusé.',

    // ── Formats ──────────────────────────────────────────────────────────────
    'email'     => 'Le champ :field doit être une adresse e-mail valide.',
    'url'       => 'Le champ :field doit être une URL valide.',
    'http_url'  => 'Le champ :field doit être une URL http(s) valide.',
    'domain'    => 'Le champ :field doit être un domaine valide.',
    'timezone'  => 'Le champ :field doit être un fuseau horaire valide.',
    'locale'    => 'Le champ :field doit être une locale au format en_US.',
    'currency'  => 'Le champ :field doit être un code ISO 4217 à 3 lettres.',
    'phone'     => 'Le champ :field doit comporter 7 à 15 chiffres (+ initial facultatif).',
    'e164'      => 'Le champ :field doit être un numéro de téléphone E.164 valide.',
    'uuid'      => 'Le champ :field doit être un UUID valide.',
    'ulid'      => 'Le champ :field doit être un ULID valide.',
    'slug'      => 'Le champ :field doit être un slug en minuscules séparé par des tirets.',
    'username'  => 'Le champ :field doit comporter au moins 3 lettres, chiffres ou tirets bas.',
    'json'      => 'Le champ :field doit être du JSON valide.',
    'base64'    => 'Le champ :field doit être du base64 valide.',
    'hex_color' => 'Le champ :field doit être une couleur hexadécimale valide.',
    'ip'        => 'Le champ :field doit être une adresse IP valide.',
    'ipv4'      => 'Le champ :field doit être une adresse IPv4 valide.',
    'ipv6'      => 'Le champ :field doit être une adresse IPv6 valide.',
    'mac_address' => 'Le champ :field doit être une adresse MAC valide.',

    // ── Classes de caractères ────────────────────────────────────────────────
    'alpha'               => 'Le champ :field ne peut contenir que des lettres.',
    'alpha_num'           => 'Le champ :field ne peut contenir que des lettres et des chiffres.',
    'alpha_dash'          => 'Le champ :field ne peut contenir que des lettres, chiffres, tirets et tirets bas.',
    'alpha_space'         => 'Le champ :field ne peut contenir que des lettres et des espaces.',
    'alpha_numeric_punct' => 'Le champ :field contient un caractère invalide.',
    'ascii'               => 'Le champ :field ne peut contenir que des caractères ASCII.',
    'lowercase'           => 'Le champ :field doit être en minuscules.',
    'uppercase'           => 'Le champ :field doit être en majuscules.',
    'hex'                 => 'Le champ :field doit être hexadécimal.',

    // ── Nombres ──────────────────────────────────────────────────────────────
    'min'                => 'Le champ :field doit être au moins :min.',
    'max'                => 'Le champ :field ne doit pas dépasser :max.',
    'between'            => 'Le champ :field doit être compris entre :min et :max.',
    'size'               => 'Le champ :field doit être de taille :param.',
    'gt'                 => 'Le champ :field doit être supérieur à :param.',
    'gte'                => 'Le champ :field doit être au moins :param.',
    'lt'                 => 'Le champ :field doit être inférieur à :param.',
    'lte'                => 'Le champ :field ne doit pas dépasser :param.',
    'digits'             => 'Le champ :field ne doit contenir que des chiffres.',
    'digits_between'     => 'Le champ :field comporte un nombre de chiffres invalide.',
    'min_digits'         => 'Le champ :field doit comporter au moins :param chiffres.',
    'max_digits'         => 'Le champ :field ne doit pas dépasser :param chiffres.',
    'decimal'            => 'Le champ :field doit être un nombre décimal.',
    'multiple_of'        => 'Le champ :field doit être un multiple de :param.',
    'is_natural'         => 'Le champ :field doit être un entier positif ou nul.',
    'is_natural_no_zero' => 'Le champ :field doit être un entier strictement positif.',

    // ── Dates ────────────────────────────────────────────────────────────────
    'date'        => 'Le champ :field doit être une date valide.',
    'date_format' => 'Le champ :field ne correspond pas au format attendu.',
    'before'      => 'Le champ :field doit être une date antérieure à :param.',
    'after'       => 'Le champ :field doit être une date postérieure à :param.',

    // ── Comparaison et appartenance ──────────────────────────────────────────
    'in'                => 'La valeur sélectionnée pour :field est invalide.',
    'not_in'            => 'La valeur sélectionnée pour :field est invalide.',
    'enum'              => 'La valeur sélectionnée pour :field est invalide.',
    'distinct'          => 'Le champ :field contient des valeurs en double.',
    'regex'             => 'Le format du champ :field est invalide.',
    'same'              => 'Le champ :field doit correspondre à :other.',
    'different'         => 'Le champ :field doit être différent de :other.',
    'confirmed'         => 'La confirmation du champ :field ne correspond pas.',
    'starts_with'       => 'Le champ :field a un préfixe invalide.',
    'ends_with'         => 'Le champ :field a un suffixe invalide.',
    'doesnt_start_with' => 'Le champ :field a un préfixe interdit.',
    'doesnt_end_with'   => 'Le champ :field a un suffixe interdit.',

    // ── Pack FinancialRules ──────────────────────────────────────────────────
    'luhn'        => 'Le champ :field a échoué à sa somme de contrôle.',
    'credit_card' => 'Le champ :field doit être un numéro de carte valide.',
    'cvv'         => 'Le champ :field doit être un code de sécurité à 3 ou 4 chiffres.',
    'iban'        => 'Le champ :field doit être un IBAN valide.',
    'bic'         => 'Le champ :field doit être un code BIC/SWIFT valide.',

    /*
     * ── Noms de champs ──────────────────────────────────────────────────────
     * Ce par quoi :field est remplacé. Sans cela, le lecteur voit le nom
     * technique du champ (« firstName »).
     *
     * Une clé absente ici est mise en forme automatiquement (firstName →
     * "first name") — donc en anglais. Traduire un champ demande donc bien une
     * entrée ici. Les GROUPES de langue FUSIONNENT dans la cascade : un projet
     * n'ajoute que ses propres champs, sans recopier ce fichier.
     */
    'attributes' => [
        'email'                 => 'e-mail',
        'password'              => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'passwordConfirmation'  => 'confirmation du mot de passe',
        'url'                   => 'URL',
        'uri'                   => 'URI',
        'ip'                    => 'adresse IP',
        'dob'                   => 'date de naissance',
        'tos'                   => "conditions d'utilisation",
        'id'                    => 'identifiant',
        'uuid'                  => 'UUID',
        'sku'                   => 'référence',
        'vat'                   => 'numéro de TVA',
        'iban'                  => 'IBAN',
        'bic'                   => 'code BIC/SWIFT',
        'cvv'                   => 'code de sécurité',
    ],
];
