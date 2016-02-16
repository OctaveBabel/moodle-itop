<?php

$string['bycity'] = 'Par ville';
$string['byname'] = 'Par nom';
$string['configbuildteachercategory'] = 'Contruire la catégorie enseignant';
$string['configbuildteachercategorydesc'] = 'Si actif, tout nouvel enseignant importé se verra attribuer une catégorie de cours à son nom dont il sera gestionnaire dans l\'espace pédagogique des enseignants.';
$string['configcronenable'] = 'Intégration par le cron';
$string['configcronenabledesc'] = 'Activez cette option si vous voulez laisser effectuer l\'intégration des utilisateurs par le cron Moodle. Désactivez cette option si vous prévoyez de planifier ce traitement par vous-même (mode CLI).';
$string['configcohortindex'] = 'Préfixe de cohorte';
$string['configcohortindexdesc'] = 'Ce préfixe est ajouté aux noms de cohortes générées. Ce prefixe devrait être changé lors d\'un changement de session pédagogique (année scolaire) pour générer et maintenir un nouveau jeu de cohortes pour la session.';
$string['configcrontime'] = 'Heure de traitement';
$string['configfakemaildomain'] = 'Domaine des mails autogénérés ';
$string['configfakemaildomaindesc'] = ' Domaine utilisé pour générer des adresses mail factices lorsqu\'elles sont manquantes dans les profils importés';
$string['configgetid'] = 'ID de structure';
$string['configgetinstitutionidservice'] = 'Chercher un ID d\'établissement';
$string['configinstitutionid'] = 'ID Etablissement';
$string['configinstitutioniddesc'] = 'L\'identifiant de jointure d\'établissement Education Nationale';
$string['configlastsyncdate'] = 'Dernière synchro';
$string['configlastsyncdatedesc'] = 'Dernière date de synchro. Si vous changez cette date, la prochaine synchro considèrera tous les utilisateur créés ou modifiés à partir de cette date.';
$string['configrealauth'] = 'Méthode d\'authentification effective';
$string['configrealauthdesc'] = 'Ce réglage définit la méthode d\'authentification à attribuer aux comptes synchronisés de l\'ENT, indépendamment du plugin utilisé pour contacter l\'annuaire.';
$string['configstructurecontext'] = 'Contexte LDAP';
$string['configstructurecontextdesc'] = 'contexte(base DN) LDAP où sont stockées les définitions d\'établissement';
$string['configstructureid'] = 'Identifiant';
$string['configstructureiddesc'] = 'L\'attribut portant l\'dentifiant unique d\'établissement';
$string['configstructurename'] = 'Nom courant';
$string['configstructurenamedesc'] = 'L\'attribut portant le nom courant de la structure';
$string['configsyncenable'] = 'Actif';
$string['configsyncenabledesc'] = 'Active la synchronisation régulière des données ENT (CLI). Si désactivé, le script de synchornisation n\'aura aucun effet même s\'il est lancé par cron.';
$string['configteacherstubcategory'] = 'Container espaces enseignants';
$string['configteacherstubcategorydesc'] = 'La catégorie contenant les containers de cours propres aux enseignants';
$string['configupdateinstitutionstructure'] = 'Mettre à jour la structure établissement';
$string['configupdateinstitutionstructuredesc'] = 'Si actif, la structure de l\'établissement (catégories de classes) est mise à jour avant chaque synchronisation.';
$string['datasync'] = 'Synchronisation de données ENT';
$string['dbinsertuser'] = 'ALIMENTATION : Création utilisateur {$a->username} [{$a->idnumber}] role : {$a->usertype} / {$a->function}';
$string['dbinsertusersimul'] = 'SIMULATION : Création utilisateur {$a->username} [{$a->idnumber}] role : {$a->usertype} / {$a->function}';
$string['dbupdateuser'] = 'ALIMENTATION : Mise à jour utilisateur {$a->username} [{$a->idnumber}] role : {$a->usertype} / {$a->function} status: {$a->status}';
$string['dbupdateusersimul'] = 'SIMULATION : Mise à jour utilisateur {$a->username} [{$a->idnumber}] role : {$a->usertype} / {$a->function} statut : {$a->status}';
$string['getinstitutionidservice'] = 'Recherche d\'identifiants d\'établissements';
$string['id'] = 'Identifiant RNE';
$string['lastrun'] = 'Dernière exécution {$a}';
$string['minduration'] = 'Durée min';
$string['maxduration'] = 'Durée max';
$string['noresults'] = 'Aucun résultat';
$string['pluginname'] = 'Installation spécifique Moodle ENT';
$string['search'] = 'Recherche';
$string['structuresearch'] = 'Paramètres pour la recherche de structures';
$string['syncdisabled'] = 'La synchro est désactivée sur ce site';
$string['syncbench'] = 'Mesure des temps de synchronisation';
$string['synctimes'] = 'Temps de synchro';
$string['synctime'] = 'Temps de syncro';
$string['syncbenchreportdesc'] = 'Un <a href="{$a}">rapport sur les temps de synchronisation</a>';
$string['synctimetitle'] = 'Mesure des temps de chargement/mise à jour utilisateurs';
$string['reset'] = 'Mettre à zéro les statistiques';
$string['resetallvnodes'] = 'Mettre à zéro toutes les statistiques';

$string['inserts'] = 'Insertions (utilisateurs ajoutés)';
$string['updates'] = 'Mises à jour (utilisateurs modifiés)';
$string['inserterrors'] = 'Erreurs d\'insertion';
$string['updateerrors'] = 'Erreur de mises à jour';
$string['overtimes'] = 'Dépassement de temps critique (> {$a} secs)';
$string['overtime'] = 'Dépassements';
$string['meantime'] = 'Moyenne';
$string['normalmeantime'] = 'Moyenne usuelle (sans dépassements)';
$string['simulateuserdelete'] = 'SIMULATION : Suppression de l\'utilisateur {$a} ';

$string['syncusers'] = 'Synchronisation des données utilisateurs';
$string['syncusersdesc'] = 'Paramètres utilisés par l\'outil de synchronisation des données utilisateur.';
$string['datasyncsettings'] = 'Paramètres de synchronisation';
$string['configmaildisplay'] = 'Affichage de l\'email';
$string['configmaildisplaydesc'] = 'Ce paramètre permet de choisir la visibilité des adresses email importées.';

$string['synchroniseusers'] = 'Synchronisation des données utilisateurs';
$string['force'] = 'Forcer la synchronisation';
$string['backtosettings'] = 'Revenir aux paramétrages';
