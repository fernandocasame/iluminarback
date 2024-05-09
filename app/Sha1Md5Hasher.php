// app/Sha1Md5Hasher.php

<?php

namespace App;

use Illuminate\Hashing\Hasher;

class Sha1Md5Hasher extends Hasher
{
    public function make($value, array $options = [])
    {
        return sha1(md5($value));
    }

    public function check($value, $hashedValue, array $options = [])
    {
        return $this->make($value) === $hashedValue;
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return false;
    }
}
