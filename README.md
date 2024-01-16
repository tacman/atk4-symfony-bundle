WIP Beta Repository

usage
```
composer create-project symfony/skeleton:"^6.3.8" my_project_directory
```

```
cd my_project_directory
```

by default symfony sets to stable
```
composer config minimum-stability dev
```

```
composer require abbadon1334/symfony-atk4-bundle:"*"

```
will add bundle config + bundle files in public folder

in bin/console there is a useful command to rebuild the database during development
the rebuild is based on models 
```

```
bin/console models:rebuild -p src/Models
```

Atk controllers can be created easy using attribute `#[Atk4Controller]`
```
a simple controller can be created in this way:
```

use Atk4\Symfony\Module\Atk4App;
use Atk4\Symfony\Module\Atk4Controller;
use Atk4\Symfony\Module\Atk4App;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Atk4\Ui\Header;

#[Atk4Controller]
class Homepage extends AbstractController
{
    // Symfony built in injection
    public function __construct(
        protected Atk4App $atk4app,
        protected Security $security
    ) {
    }

    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        $app = $this->atk4app->getApp();

        $app->initLayout([Centered::class]);
        
        Header::addTo($app, ['Demo']);
    }
}

```
