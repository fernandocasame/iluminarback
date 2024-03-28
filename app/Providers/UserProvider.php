<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class CustomUserProvider implements UserProvider
{
    // Otros métodos de la interfaz UserProvider...

    public function validateCredentials(UserContract $user, array $credentials)
    {
        // Aquí puedes cambiar la lógica de validación de contraseña según tus necesidades
        $plain = $credentials['password'];
        $hashed_value = $user->getAuthPassword();

        // Por ejemplo, puedes comparar la contraseña en texto plano con el valor hash almacenado
        // utilizando cualquier algoritmo de hash que prefieras
        // En este ejemplo, se utiliza la función hash() con el algoritmo sha256
        return hash('sha256', $plain) === $hashed_value;
    }
}
