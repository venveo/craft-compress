<?php

namespace venveo\compress\errors;

/**
 * Class CompressException
 *
 * @author Venveo
 * @since 1.0.0
 */
class CompressException extends \Exception
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'Compress Exception';
    }
}
