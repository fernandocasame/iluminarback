// app/Sha1Md5Hasher.php

<?php

namespace App;

use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class Sha1Md5Hasher implements HasherContract
{
    public function make($value, array $options = [])
    {
        return sha1(md5($value));
    }

    public function check($value, $hashedValue, array $options = [])
    {
        return $hashedValue === sha1(md5($value));
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return false;
    }
}
