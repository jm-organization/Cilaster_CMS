<?php
/**
 * @Created in JM Organization.
 * @Author: Magicmen
 *
 * @Date: 19.08.2017
 * @Time: 21:00
 *
 * @documentation: Генератор видов, Реализует отображение содержания страниц.
 */

namespace Cilaster\API;


use Cilaster\API\CilasterException\CException;
use Cilaster\API\AuthManager\Identity;
use Cilaster\API\CilasterException\ExceptionGenerator;
use Cilaster\API\CilasterException\MvcException;
use Cilaster\API\Http\Mvc;
use Cilaster\API\Http\Server;
use Cilaster\API\Request\GetRequest;
use Cilaster\Core\Config;
use Cilaster\Core\Constant;
use Cilaster\Core\Router;

class View {
	public $title;

	public $short_title;

	public $view_port = false;

	public $theme;

	public function setTitle($title) {
		return $this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setShortTitle( $short_title ) {
		$this->short_title = $short_title;
	}

	public function getShortTitle() {
		return $this->short_title;
	}

	public function setViewPort( $view_port ) {
		$this->view_port = $view_port;
	}

	public function isViewPort() {
		return $this->view_port;
	}

	public function setTheme( $theme ) {
		$this->theme = $theme;
	}

	public function getTheme() {
		return $this->theme;
	}

	/**
	 * View constructor.
	 *
	 * 	Инициализация вида приложения.
	 *
	 * @param string $theme
	 */
	public function __construct($theme = 'default') {
		// TODO: View Port, metatags;

		$this->setTitle($this->generateTitle());
		$this->setTheme($theme);

		return $this;
	}

	/**
	 * @function: generateTitle
	 *
	 * @documentation:
	 * 	В зависимости от настроек и маршрута
	 * генерирует заголовок страницы.
	 *
	 * @return string
	 */
	public function generateTitle() {
		$config = (new Config('application'))->get('title');

		$config_title = trim($config);
		$this->setShortTitle(($config_title != '')?$config_title:'Cilaster CMS');

		if (!IS_INSTALLED) {
			$page_config_title = str_replace('{object}', (new GetRequest())->content()->get('product'), Router::$route->title);
		}

		$page_config_title = Router::$route->title;

		$page_title = (($page_config_title)?$page_config_title.' | ':'').$this->getShortTitle();

		return $page_title;
	}

	/**
	 * @function: generate
	 *
	 * @documentation:
	 * 	Генерирует страницу в зависимости от настроек шаблона.
	 * Запускает маршрутизатор для выполнения соответствующего метода Controller
	 *
	 * @throws \Exception
	 */
	public function generate() {
		$layout = str_replace('\..', '', $this->getTheme()).'_layout';

		global $content;
		try {
			Router::start();

			$content = Router::getContent();
		} catch (\Exception $e) {
			$content = (new ExceptionGenerator($e))->get();
		}

		if ($layout != 'no_layout') {
			require Constant::THEMES_ROOT.$this->getTheme().$layout.".php";
		}
	}

	/**
	 * @function: generateErrorPage
	 *
	 * @documentation:
	 * 	Генерирует страницу ошибки.
	 *
	 * @return mixed
	 */
	public function generateErrorPage() {
		return require_once Constant::THEMES_ROOT.'\\'.$this->getTheme().'\\404.html';
	}

	/**
	 * @function: basePath
	 *
	 * @documentation:
	 * 	Стоит использовать для подключений
	 * таблиц каскадных стилей, javascript-файлов, изображений.
	 * Берёт ресурсы из директории темы.
	 *
	 * @param null $path
	 *
	 * @return null
	 */
	public function basePath($path = null) {
		$route = str_replace('.php', '', trim(Router::$route->route, '/'));
		$server = new Server();

		$url_path = "//".$server->getName();
		$dir = ($route == 'install' || $route == 'update')?'vcs':'themes';
		$theme = ($route != 'install' && $route != 'update')?($this->getTheme().'/'):'';
		$file_path = '/'."$dir/".$theme."$path";

		return $url_path.(($path)?$file_path:'');
	}

	private function rootPath() { return $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME']."/themes"; }

	public function bootstrapPath($file) {
		$bootstrap_root = Constant::THEMES_ROOT.'_bootstrap/'.$file;

		if (!file_exists($bootstrap_root)) return $this->basePath();

		return $this->rootPath().$file;
	}

	public function pluginsPath($file) {
		$plugins_root = Constant::THEMES_ROOT.'_plugins/'.$file;

		if (!file_exists($plugins_root)) return $this->basePath();

		return $this->rootPath().$file;
	}

	/**
	 * @function: render
	 *
	 * @documentation:
	 * 	Запускает процес отрисовки пользовательского интерфейса.
	 * В качестве принимающих параметров принимает ассоциативный масив с данными.
	 * Далее эти данные могут будуть выведены на пользовательских страницах.
	 *
	 * 	Используеться как инструмент для отрисовки Форм и прочих данных,
	 * пришедших с сервера.
	 *
	 * @param $view
	 * @param array $variables
	 *
	 * @return string
	 */
	public function render($view, $variables = []) {
		if ($view) {
			$view_root = Constant::MAIN_ROOT.'/';
			$view_path = Router::$route->viewpath."/$view.phtml";

			if (!file_exists($view_root.$view_path)) {
				$method = Router::$route->controller.'::'.Router::$route->action.'()';

				throw MvcException::UndefinedViewRoute($view_path, $method);
			} else {
				ob_start();

				if (is_array($variables)) {
					foreach ($variables as $key => $value) {
						${$key} = $value;
					}
				}

				$mvc = new Mvc();

				include_once $view_root.$view_path;
			}

			return ob_get_clean();
		}
	}

	/**
	 * @function: insert
	 *
	 * @documentation:
	 * 	Делает вставку файлов из директории \include
	 * в корне сайта.
	 *
	 * @param $file_path
	 * @param $file_extension
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function insert($file_path, $file_extension) {
		$root = Constant::THEMES_ROOT.'/'.$this->getTheme();
		$reserve = ['install', 'update'];
		$route = str_replace('/', '',
			str_replace('.php', '', Router::$route->route)
		);

		if (array_key_exists($route, array_flip($reserve))) {
			$root = Constant::MAIN_ROOT.'/vcs';
		}

		$path = str_replace('/', '\\', "$root/_includes/$file_path$file_extension");

		try {
			if (!file_exists($path)) {
				throw MvcException::InsertedFileNotFound($path);
			}

			include $path;
		} catch (\Exception $e) {
			(new ExceptionGenerator($e))->render();
		}
	}

	public function navBar( $settings = [ ] ) {
		if (!empty( $settings )) {
			$nav_bar = '';
			$nav_links = '';
			$nav_bar .= ($settings['logotype'])?'<div class="logotype"><a href="/"></a></div>':'';

			foreach ($settings['links'] as $key => $value) {
				$nav_links .= '<li><a href="'.$value.'">'.$key.'</a></li>';
			}

			$nav_bar .= '<ul class="'.$settings['nav-class'].'">'.$nav_links.'</ul>';

			return '<nav>'.$nav_bar.'</nav>';
		}
	}

	public function currentRoute($params=null) {
		$uri = Router::get_uri();

		if (!is_string($uri) && $uri == 'index') {
			return $uri;
		} elseif (is_string($uri)) {
			if (isset( $params )) {
				switch ($params) {
					case 'first':
						return explode('/', $uri)[0];
						break;
					case 'last':
						return max(explode('/', $uri));
						break;
					default:
						if (is_int( $params )) {
							return explode( '/', $uri )[ $params ];
						} else {
							return false;
						}
						break;
				}
			} else {
				return max(explode('/', $uri));
			}
		} else {
			return false;
		}
	}

	public function identity() {
		return new Identity();
	}

	public function adminBar() {
		// TODO: Проверка прав пользователя
		$admin_bar = 'admin-panel/admin_bar_layout.php';

		if (file_exists(Constant::MAIN_ROOT.'\\'.$admin_bar)) {
			return "<iframe width='100%' height='32px' src='$admin_bar' class='admin-bar-frame'><div class='admin-bar'>
						<p>К сожалению, ваш браузер не подерживает <code>&lt;iframe&gt;</code>.</p>
						<p style='float: right;'><a href='/themes/admin/path/index.php?admin' target='_blank'>Панель администратора</a></p>
					</div></iframe>";
		}

		return null;
	}
}