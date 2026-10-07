<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPEM_RSVP
{
    public function __construct()
    {
        add_action(
            'wp_ajax_wpem_rsvp',
            [$this, 'save_rsvp']
        );

        add_action(
            'wp_ajax_nopriv_wpem_rsvp',
            [$this, 'no_access']
        );

        add_filter(
            'the_content',
            [$this, 'append_to_event'],
            999
        );
    }

    public function no_access()
    {
        wp_send_json_error();
    }

    public function save_rsvp()
    {
        check_ajax_referer(
            'wpem_rsvp_nonce',
            'nonce'
        );

        if (!is_user_logged_in()) {
            wp_send_json_error();
        }

        $event_id = absint($_POST['event_id']);
        $status   = sanitize_text_field($_POST['status']);

        if (!in_array($status, ['attending', 'absent'])) {
            wp_send_json_error();
        }

        update_user_meta(
            get_current_user_id(),
            'wpem_rsvp_' . $event_id,
            $status
        );

        wp_send_json_success();
    }

    public function append_to_event($content)
    {
        if (!is_singular('event_listing')) {
            return $content;
        }

        ob_start();

        $this->render();

        return $content . ob_get_clean();
    }

    private function get_current_status($event_id)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        return get_user_meta(
            get_current_user_id(),
            'wpem_rsvp_' . $event_id,
            true
        );
    }

    private function get_absents($event_id)
    {
        return get_users([
            'meta_key'   => 'wpem_rsvp_' . $event_id,
            'meta_value' => 'absent',
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]);
    }

    private function get_attending($event_id)
    {
        return get_users([
            'meta_key'   => 'wpem_rsvp_' . $event_id,
            'meta_value' => 'attending',
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]);
    }

    public function render()
    {
        $event_id = get_the_ID();

        $status = $this->get_current_status(
            $event_id
        );

        $absents = $this->get_absents(
            $event_id
        );

        $participants = $this->get_attending(
            $event_id
        );
        ?>

        <div class="wpem-rsvp-box">

            <h2>Présence</h2>

            <?php if (is_user_logged_in()) : ?>

                <div class="wpem-current-status">

                    <strong>Votre réponse :</strong>

                    <?php
                    if ($status === 'attending') {
                        echo '✅ Mon enfant participe';
                    } elseif ($status === 'absent') {
                        echo '❌ Mon enfant est absent';
                    } else {
                        echo 'Aucune réponse';
                    }
                    ?>

                </div>

                <div class="wpem-rsvp-buttons">

                    <button
                        type="button"
                        class="wpem-rsvp-btn attending"
                        data-status="attending">

                        Mon enfant participe
                        (<?php echo count($participants); ?>)

                    </button>

                    <button
                        type="button"
                        class="wpem-rsvp-btn absent"
                        data-status="absent">

                        Mon enfant est absent
                        (<?php echo count($absents); ?>)

                    </button>

                </div>

            <?php endif; ?>

            <div class="wpem-rsvp-section">

                <h3>
                    ❌ Absents
                    (<?php echo count($absents); ?>)
                </h3>

                <?php foreach ($absents as $user) : ?>

                    <div class="wpem-rsvp-user">

                        <?php echo get_avatar($user->ID, 48); ?>

                        <span>
                            <?php echo esc_html($user->display_name); ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="wpem-rsvp-section">

                <h3>
                    ✅ Participants
                    (<?php echo count($participants); ?>)
                </h3>

                <?php foreach ($participants as $user) : ?>

    <div class="wpem-rsvp-user">

        <?php echo get_avatar($user->ID, 48); ?>

        <span>
            <?php echo esc_html($user->display_name); ?>
        </span>

    </div>

<?php endforeach; ?>

            </div>

        </div>

        <script>

        jQuery(function($){

            $('.wpem-rsvp-btn').on(
                'click',
                function(){

                    $.post(
                        '<?php echo admin_url('admin-ajax.php'); ?>',
                        {
                            action : 'wpem_rsvp',
                            nonce : '<?php echo wp_create_nonce('wpem_rsvp_nonce'); ?>',
                            event_id : <?php echo (int) $event_id; ?>,
                            status : $(this).data('status')
                        },
                        function(){
                            location.reload();
                        }
                    );

                }
            );

        });

        </script>

        <?php
    }
}

new WPEM_RSVP();