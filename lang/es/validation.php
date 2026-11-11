<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'size' => [
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'regex' => 'El formato del campo :attribute no es válido.',
    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
        'file' => 'El archivo :attribute no debe superar los :max kilobytes.',
    ],
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'extensions' => 'El campo :attribute debe tener una extensión válida: :values.',
    'attributes' => [
        'pin' => 'PIN',
        'tipo_documento' => 'tipo de documento',
        'numero_documento' => 'número de documento',
        'nombre_completo' => 'nombre completo',
        'telefono' => 'teléfono',
        'correo' => 'correo electrónico',
        'foto' => 'fotografía',
    ],
];
