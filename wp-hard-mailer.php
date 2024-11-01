<?php
/*
   Plugin Name: Wp Hard Mailer
   Plugin URI: http://dgmike.com.br
   Description: Create two textareas, one for create a form template and other for a mail template. The form can be putted in posts, pages or sidebars.
   Version: 1.1.2
   Author: DGmike
   Author URI: http://dgmike.com.br
*/


// Only run this script if the class does not exists
if (!class_exists('WpHardMailer')):


class WpHardMailer {
  static $wpdb;

  /**
   * Initializate the plugin. It calls the wpdb object and add the action for add settings page
   *
   * @return void
   */
  static function init() {
    if ( !is_null(WpHardMailer::$wpdb) ) return WpHardMailer::$wpdb;
    global $wpdb;
    register_activation_hook(__FILE__, array('WpHardMailer', 'install'));
    WpHardMailer::$wpdb =& $wpdb;
    add_action('admin_menu', array('WpHardMailer', 'menuOptions'));
    add_shortcode('wphm', array('WpHardMailer', 'short_code'));
    return WpHardMailer::$wpdb;
  }

  /**
   * Creates a option for menu settings for plugin options
   *
   * @return void
   */
  static function menuOptions() {
    if ( function_exists('add_submenu_page') )
      add_submenu_page('options-general.php', __('WP Hard Mailer'), __('Wp Hard Mailer'), 'manage_options', 'WpHardMailer', array('WpHardMailer', 'config_page'));
    if ( function_exists('add_action') )
      add_action('settings_page_WpHardMailerEdit', array('WpHardMailer', 'edit_page'));
  }

  static function table($table) {
    $wpdb =& self::init();
    return "{$wpdb->prefix}WpHardMailer_{$table}";
  }

  /**
   * Page for configure the plugin
   *
   * @return void
   */
  static function config_page($message='') {
    $wpdb =& self::init();
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if (isset($_GET['delete'])) {
      $delete = (int) $_GET['delete'];
      $q = $wpdb->prepare('DELETE FROM '.self::table('form').' WHERE id_form = %s', $delete);
      $wpdb->query($q);
      $message = __('Form template deleted successfully.');
    }
    if ($page < 1) $page = 1;
    $itens = self::forms ($page);

    ?>

<div class="wrap">
<h2><?php _e('Wp Hard Mailer'); ?> <a href="?page=WpHardMailerEdit&amp;id=0" class="button"><?php _e('New'); ?></a></h2>
  <?php if ($message): ?><div class="updated fade"><?php echo $message ?></div><?php endif ?>
  <table class="widefat fixed">
    <thead>
      <tr>
        <th scope="col"><?php _e('Template Name'); ?></th>
        <th scope="col"><?php _e('E-mails'); ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $item): ?>
      <tr>
        <td>
          <strong>[wphm name="<?php echo $item->name ?>"]</strong>
          <div class="row-actions">
            <span class="edit">
              <a title="<?php _e('Edit this mail form') ?>" href="options-general.php?page=WpHardMailerEdit&amp;id=<?php echo $item->id_form ?>"><?php _e('Edit') ?></a>
            </span>
            <span class="edit">
              <a title="<?php _e('Delete this mail form') ?>" href="options-general.php?page=WpHardMailer&amp;delete=<?php echo $item->id_form ?>"><?php _e('Delete') ?></a>
            </span>
          </div>
        </td>
        <td><?php echo htmlentities($item->mail) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

    <?php
  }


  function forms ( $page = 1, $per_page = 50 ) {
    $wpdb =& self::init();

    $page = (int) $page;
    if ( $page < 2 ) $page = 1;

    $per_page = (int) $per_page;
    if ( $per_page < 1 ) $per_page = 50;

    $start = ( $page - 1 ) * $per_page;
    $end = $start + $per_page;

    return $wpdb->get_results( "SELECT * FROM ".self::table('form')." LIMIT $start, $end");
  }

  static function post ( $name, $default='', $wp_hard_mailer = true ) {
    if ( $wp_hard_mailer ) $name = "wp_hard_mailer_{$name}";
    $post = isset($_POST[$name]) ? $_POST[$name] : $default;
    if (get_magic_quotes_gpc()) $post = stripslashes($post);
    return htmlentities($post);
  }

  static function edit_page() {
    $wpdb =& self::init();
    if ( !isset($_GET['id']) || !ereg('^[0-9]+$', $_GET['id']) )
      return self::config_page(__('Invalid Form Template'));

    if ($_GET['id']==='0') {
      $form = (object) array(
        'name'     => self::post('name'),
        'mail'     => self::post('mail'),
        'success'  => self::post('success'),
        'fail'     => self::post('fail'),
        'form'     => self::post('form'),
        'template' => self::post('template'),
      );
    } else {
      $form = $wpdb->get_row('SELECT * FROM '.self::table('form').' WHERE id_form = '.((int) $_GET['id']).' LIMIT 1');
      if (!$form) return self::config_page(__('Invalid Form Template'));
      $form = (object) array(
        'name'     => self::post('name', $form->name),
        'mail'     => self::post('mail', $form->mail),
        'fail'     => self::post('fail', $form->fail),
        'success'  => self::post('success', $form->success),
        'form'     => self::post('form', $form->form),
        'template' => self::post('template', $form->template),
      );
    }

    list($save, $message) = ( self::try_save( $form, (int) $_GET['id'] ) );
    if ($save==true) {
      return self::config_page(__('Form template saved!'));
    }
  ?>

<div class="wrap">
  <h2><?php _e('Edit Form'); ?></h2>
  <?php if ($message): ?><div class="updated fade"><?php echo $message ?></div><?php endif ?>
  <form method="post" action="options-general.php?page=WpHardMailerEdit&amp;id=<?php echo $_GET['id'] ?>">
    <table class="form-table">
      <tr align="top">
        <th scope="row"><?php _e('Name'); ?></th>
        <td>
          <input type="text" name="wp_hard_mailer_name" class="regular-text" value="<?php echo $form->name ?>" /><br />
          <span class="description"><?php _e('This name will be used on pages/posts/widgets'); ?></span>
        </td>
      </tr>
      <tr align="top">
        <th scope="row"><?php _e('E-mails'); ?></th>
        <td>
          <input type="text" name="wp_hard_mailer_mail" class="regular-text" value="<?php echo $form->mail ?>" /><br />
          <span class="description"><?php _e('Separated by semicolon. You can use this format: Name &lt;email@host.com&gt;; Other Name &lt;other@mail.net&gt;'); ?></span>
        </td>
      </tr>
      <tr align="top">
        <th scope="row"> <?php _e('Fail Message'); ?> </th>
        <td> <textarea cols="70" rows="4" name="wp_hard_mailer_fail"><?php echo $form->fail ?></textarea> </td>
      </tr>
      <tr align="top">
        <th scope="row"> <?php _e('Success Message'); ?> </th>
        <td> <textarea cols="70" rows="4" name="wp_hard_mailer_success"><?php echo $form->success ?></textarea> </td>
      </tr>
      <tr align="top">
        <th scope="row">
          <?php _e('Form Template'); ?>
        </th>
        <td>
          <strong>&lt;form action="" method="post"&gt;</strong><br />
          <textarea cols="70" rows="10" name="wp_hard_mailer_form"><?php echo $form->form ?></textarea>
          <br /><strong>&lt;/form&gt;</strong>
        </td>
      </tr>
      <tr align="top">
        <th scope="row">
          <?php _e('E-mail Template'); ?>
        </th>
        <td>
          <textarea cols="70" rows="10" name="wp_hard_mailer_template"><?php echo $form->template ?></textarea><br />
          <span class="description"><?php _e('Use each name in uppercase inside <strong>[</strong> and <strong>]</strong> like <strong>[AGE]</strong>.'); ?></span>
        </td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" value="<?php _e('Save'); ?>" />
    </p>
  </form>
</div>

  <?php
  }

  static function try_save ( $form, $id ) {
    $wpdb =& self::init();
    if (!$_POST) return array(false, false);
    if (!$name=self::post('name'))
      return array(false, __('Name field is required'));
    $not_this = $id ? ' AND id_form != '.$id : '';
    $item = $wpdb->get_row('SELECT * FROM '.self::table('form').' WHERE name = "'.$name.'"'.$not_this);
    if ($item) return array(false, __("The name form already aexists."));
    $form->mail = html_entity_decode($form->mail);
    $form->fail = html_entity_decode($form->fail);
    $form->success = html_entity_decode($form->success);
    $form->form = html_entity_decode($form->form);
    $form->template = html_entity_decode($form->template);
    if ($_GET['id'])
      $wpdb->update( self::table('form'), (array) $form, array('id_form' => (int) $_GET['id']) );
    else
      $wpdb->insert( self::table('form'), (array) $form );
    return array(true);
  }

  /**
   * Creates a table on database
   *
   * On columns you don't need to pass the id col, the id col is generated
   * by `{$table}_id`
   *
   * Use the shortcut of type for create a col on table: i for int, t for text, v for varchar(255)
   *
   * <code>array('nome v', 'idade i', 'descricao t')</code>
   *
   * @param string $table The name of the table used
   * @param array  $cols  The cols of the table
   * @return void
   */
  static function createTable($table, $cols) {
    $wpdb =& self::init();
    array_unshift($cols, "id_$table int");
    $query = array();
    foreach ($cols as $item) {
      if (strtoupper(substr($item, -2)) == ' V') $item.='archar(255)';
      if (strtoupper(substr($item, -2)) == ' T') $item.='ext';
      if (strtoupper(substr($item, -2)) == ' I') $item.='nt';
      $item = "  {$item} NOT NULL";

      if (!$query) $item .= ' AUTO_INCREMENT';
      $query[] = $item;
    }
    $query[] = "  PRIMARY KEY (id_$table)";
    $query = "CREATE TABLE ".self::table($table)." (\n". implode (",\n", $query) ."\n) ";
    $wpdb->query($query);
  }

  /**
   * Installs the plugin, creating the tables necessaries
   *
   * @use WpHardMailer::createTable
   * @return void
   */
  static function install() {
    $cols = array ('name v', 'mail t', 'success t', 'fail t', 'form t', 'template t');
    WpHardMailer::createTable ( 'form', $cols );
  }

  function short_code ($args){
    global $WpHardMailer_counter;
    if (!isset($WpHardMailer_counter) OR !$WpHardMailer_counter) $WpHardMailer_counter=0;
    $WpHardMailer_counter++;
    extract(shortcode_atts(array( 'name' => '',), $args));
    $wpdb =& self::init();
    $form = $wpdb->get_row('SELECT * FROM '.self::table('form').' WHERE name="'.$name.'" limit 1');
    if (!$form) return '';
    if ($args['mail']) $form->mail = $args['mail'];
    $wp_hard_mailer_template = md5($form->form.$WpHardMailer_counter);
    $message = '';
    if ($_POST)
      if (isset($_POST['WpHardMailer_FormTemplateID']) AND $_POST['WpHardMailer_FormTemplateID'] == $wp_hard_mailer_template)
        $message = self::sendmail( $form ) ? $form->success : $form->fail;
    return $message.'<form method="post"><input type="hidden" name="WpHardMailer_FormTemplateID" value="'.$wp_hard_mailer_template.'" />'.$form->form.'</form>';
  }

  function sendmail ( $form ) {
    $template = $form->template;
    foreach ($_POST as $key=>$value) {
      $value = gettype($value) == 'array' ? implode (', ', $value) : $value;
      $key = strtoupper($key);
      $template = str_replace("[$key]", $value, $template);
    }
    include_once(ABSPATH.'wp-includes/class-phpmailer.php');
    include_once(ABSPATH.'wp-includes/class-smtp.php');
    $mail = new PHPMailer();
    foreach (explode(';', $form->mail) as $email) {
      $email = trim($email);
      $email = preg_replace('@\|\s*(.+)\s*$@', ' <\\1>', $email);
      if (!preg_match('/<[^>]+>$/', $email)) $email = "$email <$email>";
      preg_match('/(.+)<([^>]+)>$/', $email, $matches);
      $mail->AddAddress($matches[2], $matches[1]);
    }
    $mail->Body = $template;
    $mail->Subject = '[WPHM] '.$form->name;
    return $mail->send();
  }
}

WpHardMailer::init();

function wpHardMailer($args) {
  if (gettype($args)=='string' AND strpos($args, '&') === false)
    $args = array('name' => $args);

  $default = array('name' => '', 'mail' => '', 'print' => true);
  $args = wp_parse_args( (array) $args, $default );

  $return = WpHardMailer::short_code($args);
  if ($args['print']) print $return;
  return $return;
}

endif; // endif (!class_exists)
