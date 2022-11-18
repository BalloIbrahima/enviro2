<?php

/**
 * @package Formulaire
 * @version 1.7.2
 */
/*
Plugin Name: contact_enviro2
Plugin URI: http://wordpress.org/plugins/hello-dolly/
Description: Ceci est une description de notre plugin de formulaire de cantact
Version: 1.7.2
Author URI: http://ma.tt/
*/

$email = sanitize_text_field($_POST['email']);

function Contact_Plugin()
{
  $content = '';
$content .= '<!DOCTYPE html>';
$content .= '<html lang="en">';
$content .= '<head>';
$content .= '<meta charset="UTF-8">';
$content .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
$content .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$content .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css"';
$content .= 'integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
$content .= '<link rel="stylesheet" href="/assets/css/style.css">';
$content .= '<title>Simple Form</title>';
$content .= '</head>';
$content .= '<body>';
$content .= '<div class="form-box" style="margin-top: 5%;" >';
$content .= '<div align="center"> <h1>Nous contacter</h1> </div>';

//  Les conditions


if(isset($_POST['custumcontact_submit']))
{
if(isset($_POST['email'],$_POST['nom'],$_POST['objet'],$_POST['description']) 
&& $_POST['email'] != null && $_POST['nom'] != null && $_POST['objet'] != null && $_POST['description'] != null ){

  if(filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)){

   
  $Erreurs = "Message envoyé avec succès !";  
  custumcontact_capture();
  $content.= '<div class="alert alert-success" role="alert" style="color:green; font-weight:bold; margin: 10px;text-align:center;">'.$Erreurs.'</div>';

  }else{
     $Erreurs = "L'adresse e-mail n'est pas valide";
     $content.= '<div class="alert alert-danger" role="alert" style="color:red; font-weight:bold; margin: 10px;text-align:center;">'.$Erreurs.'</div>';
  }
}
else{
  $Erreurs = "Veuillez remplir tous les champs !";
  $content.= '<div class="alert alert-danger" role="alert" style="font-weight:bold; margin: 10px;text-align:center;">'.$Erreurs.'</div>';
}
}

$content .= '<form action="" method="post">';
$content .= '<div class="form-group">';
$content .= '<label for="email">Email <span style="color:red">*</span>    </label>';
$content .= '<input class="form-control" id="email" type="email" name="email" placeholder="Entrer votre email" style="border:none;border-bottom:2px solid; ">';
$content .= '</div>';

$content .= '<div class="form-group">';
$content .= '<label for="nom">Nom <span style="color:red">*</span></label>';
$content .= '<input class="form-control" id="nom" type="text" name="nom" placeholder="Entrer votre nom" style="border:none;border-bottom:2px solid; ">';
$content .= '</div>';

$content .= '<div class="form-group">';
$content .= '<label for="object">Objet <span style="color:red">*</span> </label>';
$content .= '<input class="form-control" id="object" type="text" name="objet" placeholder="Quel est l\'objet de votre message ?"  style="border:none;border-bottom:2px solid; ">';
$content .= '</div>';
$content .= '<div class="form-group">';
$content .= '<label for="description" >Description <span style="color:red">*</span></label>';
$content .= '<textarea class="form-control" id="description" name="description" placeholder="Donner une briève description de votre message" style="border:none;border-bottom:2px solid; "></textarea>';
$content .= '</div>';
$content .= '<div align="center">';
$content .= '<input  type="submit" value="Envoyer" name="custumcontact_submit" style=" background:black;width:20%" />';
$content .= '</div>';
$content .= '</form>';
$content .= '</div>';
$content .= '</body>';
$content .= '</html>';
return $content;
}

function custumcontact_capture()
{
    if(isset($_POST['custumcontact_submit']))
    {
        // echo "<pre>";print_r($_POST);echo "</pre>";
        $email = sanitize_text_field($_POST['email']);
        $nom = sanitize_text_field($_POST['nom']);
        $objet = sanitize_text_field($_POST['objet']);
        $description = sanitize_textarea_field($_POST['description']);

     
// Administrateur system
        $to = 'siteartisanatdumali@gmail.com';
        $NOM = $nom;
        $subject = $objet;
        $message = $description.' - '.$email;
        wp_mail($to,$subject,$message);

// Visiteur du site
      $visit_email = $_POST['email'];
      $visit_objet = $objet;
      $OursMessage = "Bonjour ".$NOM
      ." Merci d’avoir contacter le service client Mali artisanat, nous vous reviendrons dans un délai de 1 Heure.
       ";

      wp_mail($visit_email,$objet,$OursMessage);
    }
}


add_shortcode('contact_enviro2','Contact_Plugin');