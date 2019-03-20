<?php

namespace AppBundle;

use AppBundle\DependencyInjection\AppBundleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new AppBundleExtension();
    }
}
