<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!--[if !mso]>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <![endif]-->

    <!--[if (mso 16)]>
    <style type="text/css">
        span {
            vertical-align: middle;
        }
    </style>
    <![endif]-->
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->

    <!--[if mso | IE]>
    <style type="text/css">
        table {
            font-family: Roboto, RobotoDraft, Helvetica, Arial, sans-serif;
        }
    </style>
    <![endif]-->

    <style type="text/css">
        <?php echo wp_kses_post( apply_filters('viwec_after_render_style','') )?>
        @media screen and (max-width: <?php echo esc_attr($responsive);?>px) {
            img {
                padding-bottom: 10px;
            }
            .viwec-responsive{
                display: inline-block !important;
            }
            td.viwec-responsive.viwec-product-responsive *,
            .viwec-responsive, .viwec-responsive table, .viwec-button-responsive {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
            }

            table.viwec-no-full-width-on-mobile {
                min-width: 0 !important;
                width: auto !important;
            }
            td.viwec-responsive tbody,
            td.viwec-responsive tbody  tr,
            td.viwec-responsive tbody  tr td {
                display: inline-block !important;
                width: 100% !important;
            }
            td.viwec-responsive table.viwec-no-full-width-on-mobile td,
            td.viwec-responsive table.viwec-no-full-width-on-mobile * td{
                width: auto !important;
            }

            td.viwec-responsive table.viwec-no-full-width-on-mobile,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default table,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default * table {
                display: table !important;
            }
            td.viwec-responsive table.viwec-no-full-width-on-mobile tbody,
            td.viwec-responsive table.viwec-no-full-width-on-mobile * tbody,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default tbody,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default * tbody {
                display: table-row-group !important;
            }
            td.viwec-responsive table.viwec-no-full-width-on-mobile tr,
            td.viwec-responsive table.viwec-no-full-width-on-mobile * tr,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default tr,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default * tr {
                display: table-row !important;
            }

            td.viwec-responsive table.viwec-no-full-width-on-mobile td,
            td.viwec-responsive table.viwec-no-full-width-on-mobile * td,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default td,
            td.viwec-responsive table.html_wc_hook .html_wc_hook_default * td {
                display: table-cell !important;
            }

            .viwec-responsive-padding {
                padding: 0 !important;
            }

            .viwec-mobile-hidden {
                display: none !important;
            }

            .viwec-responsive-center, .viwec-responsive-center p, .viwec-center-on-mobile p {
                text-align: center !important;
            }

            .viwec-mobile-50,
            table.viwec-responsive tbody  tr td.viwec-mobile-50 {
                width: 50% !important;
                min-width: 50% !important;
                max-width: 50% !important;
            }
            .viwec-mobile-50 .woocommerce-Price-amount {
                margin-right: 1px;
            }

            #viwec-responsive-min-width {
                min-width: 100% !important;
            }
        }
        #viwec-responsive-wrap {
            box-sizing:border-box;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        #viwec-responsive-wrap *{
            color:inherit;
            box-sizing: border-box;
        }
        #viwec-responsive-min-width {
            min-width: <?php echo esc_attr($width);?>px;
        }
        #viwec-responsive-wrap #body_content {
            width: 100% !important;
        }
        #viwec-responsive-wrap #outlook a {
            padding: 0;
        }

        #viwec-responsive-wrap a {
            text-decoration: none;
            word-break: break-word;
        }

        #viwec-responsive-wrap td {
            overflow: hidden;
        }

        td.viwec-row {
            background-repeat: no-repeat;
            background-size: cover;
            background-position: top;
        }
        td.viwec-responsive *{
            max-width: 100% ;
        }

        div.viwec-responsive {
            display: inline-block;
        }

        #viwec-responsive-wrap img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
            vertical-align: middle;
            background-color: transparent;
            max-width: 100%;
        }
        #viwec-responsive-wrap p {
            display: block;
            margin: 0;
            line-height: inherit;
            /*font-size: inherit;*/
        }

        #viwec-responsive-wrap small {
            display: block;
            font-size: 13px;
        }

        #viwec-transferred-content small {
            display: inline;
        }

        #viwec-transferred-content td {
            vertical-align: top;
        }
        a {
            text-decoration: none;
            word-break: break-word;
        }

        td {
            overflow: hidden;
        }

    </style>

</head>

<body vlink="#FFFFFF" <?php echo esc_attr($direction == 'rtl' ? 'rightmargin' : 'leftmargin'); ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">

<div id="viwec-responsive-wrap" style="<?php echo esc_attr( $bg_style ); ?>">
    <table border="0" cellpadding="0" cellspacing="0" height="100%" align="center" width="100%" style="margin: 0;">
        <tbody>
        <tr>
            <td style="padding: 20px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" height="100%" align="center" width="<?php echo esc_attr( $width ) ?>"
                       style="font-size: 15px; margin: 0 auto; padding: 0; border-collapse: collapse;font-family: Roboto, RobotoDraft, Helvetica, Arial, sans-serif;">
                    <tbody>
                    <tr>
                        <td align="center" valign="top" id="body_content" style="<?php echo esc_attr( $bg_style ); ?>background-size:cover;">
                            <div class="viwec-responsive-min-width">
