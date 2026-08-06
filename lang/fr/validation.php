<?php

declare(strict_types=1);

/**
 * French validation messages. :field and rule params (:min, :max, :other,
 * :values) are substituted by the Validator.
 *
 * A note on wording: French normally takes a definite article before a field
 * name ("le champ email"), but the article is gendered and :field is a runtime
 * value, so there is no way to pick "le" or "la" correctly here. These messages
 * use « Le champ :field » throughout — grammatically it treats "champ" (masculine)
 * as the head noun, which stays correct whatever :field contains. Do not
 * "fix" this to « La :field » for feminine field names; the substitution has no
 * gender information to work from.
 */
return [
    'required'  => 'Le champ :field est obligatoire.',
    'string'    => 'Le champ :field doit être une chaîne de caractères.',
    'integer'   => 'Le champ :field doit être un nombre entier.',
    'numeric'   => 'Le champ :field doit être numérique.',
    'boolean'   => 'Le champ :field doit être vrai ou faux.',
    'array'     => 'Le champ :field doit être un tableau.',
    'email'     => 'Le champ :field doit être une adresse e-mail valide.',
    'url'       => 'Le champ :field doit être une URL valide.',
    'min'       => 'Le champ :field doit être au moins :min.',
    'max'       => 'Le champ :field ne doit pas dépasser :max.',
    'between'   => 'Le champ :field doit être compris entre :min et :max.',
    'in'        => 'La valeur sélectionnée pour :field est invalide.',
    'regex'     => 'Le format du champ :field est invalide.',
    'same'      => 'Le champ :field doit correspondre à :other.',
    'different' => 'Le champ :field doit être différent de :other.',
    'confirmed' => 'La confirmation du champ :field ne correspond pas.',
];
