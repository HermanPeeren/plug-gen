<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

final class ValidationException extends \RuntimeException
{
    /** @param string[] $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
