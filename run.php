<?php
//This file was written by Valithor#5947 <@116927250145869826>
//Special thanks to keira#7829 <@297969955356540929> for helping me get this behemoth working after converting from DiscordPHP

//DO NOT VAR_DUMP GETS, most objects like GuildMember have a guild property which references all members
//Use get_class($object) to verify the main object (usually a collection)
//Use get_class($object->first())to verify you're getting the right kind of object. IE, $author_guildmember->roles should be Models\Role)
//If any of these methods resolve to a class of React\Promise\Promise you're probably passing an invalid parameter for the class
//Always subtract 1 when counting roles because everyone has an @everyone role

include __DIR__.'/vendor/autoload.php';
define('MAIN_INCLUDED', 1); 	//Token and SQL credential files are protected, this must be defined to access
ini_set('memory_limit', '-1'); 	//Unlimited memory usage

use charlottedunois\yasmin;
$loop = \React\EventLoop\Factory::create();
$discord = new \CharlotteDunois\Yasmin\Client(array(), $loop);

/*
set_exception_handler(function (Throwable $e) {
    // reconnect, log uncaught, etc etc
});
*/
 
$discord->on('disconnect', function($erMsg, $code){ //Automatically reconnect if the bot disconnects due to inactivity (Not tested)
    echo "----- BOT DISCONNECTED FROM DISCORD WITH CODE $code FOR REASON: $erMsg -----" . PHP_EOL;
	echo "RESTARTING BOT" . PHP_EOL;
	$restart_cmd = 'cmd /c "'. __DIR__  . '\run.bat"';
	//echo $restart_cmd . PHP_EOL;
	system($restart_cmd);
	die;
});

$discord->once('ready', function () use ($discord){	// Listen for events here
	echo "SETUP" . PHP_EOL;
	$version														= "V2.6";
		
	//Set status
	$discord->user->setPresence(
		array(
			'since' => null, //unix time (in milliseconds) of when the client went idle, or null if the client is not idle
			'game' => array(
				'name' => "over the Palace $version",
				'type' => 3, //0, 1, 2, 3, 4 | Game/Playing, Streaming, Listening, Watching, Custom Status
				'url' => null //stream url, is validated when type is 1, only Youtube and Twitch allowed
				/*
				Bots are only able to send name, type, and optionally url.
				As bots cannot send states or emojis, they can't make effective use of custom statuses.
				The header for a "Custom Status" may show up on their profile, but there is no actual custom status, because those fields are ignored.
				*/
			),
			'status' => 'dnd', //online, dnd, idle, invisible, offline
			'afk' => false
		)
	);
	
	//Set nickname
	//Doesn't seem to work on self
	/*
	$bot_id															= "662093882795753482";
	$setup_guild_id 												= "609814936721424400";
	$setup_guild													= $discord->guilds->get($setup_guild_id);
	$setup_member 													= $setup_guild->members->get($bot_id); //GuildMember object
	$setup_member->edit( //Must target guildmember object
		array(
			'nick' => "Palace Bot $version"
		)
	);
	*/
	
	echo "BOT IS READY" . PHP_EOL;
	
	$discord->on('message', function ($message){ //Handling of a message
		$message_content = $message->content;
		$message_content_lower = strtolower($message_content);
		/*
		*********************
		*********************
		Required includes
		*********************
		*********************
		*/
		
		include_once "custom_functions.php";
		include_once "constants.php";
		
		/*
		*********************
		*********************
		Options
		*********************
		*********************
		*/
		
		if(!CheckFile(null, "command_symbol.php")){		$command_symbol	= ";"; //Author must prefix text with this to use commands
																		  VarSave(null, "command_symbol.php", $command_symbol);
		}else 											$command_symbol = VarLoad(null, "command_symbol.php");			//Load saved option file (Not used yet, but might be later)
//		$server_invite 													= "https://discord.gg/hfqKdWW";					//Invite link to the server (commented this line to disable)
		$verify_channel_id												= "662858877343236096";							//Channel to send verification messages to (comment this line to disable)
		$getverified_channel_id											= "662070861960052767";							//Channel to send verification messages to (comment this line to disable)
//		$watch_channel_id												= "662044237411647550";
		
		if(!CheckFile(null, "react_option.php"))				$react	= true;											//Bot will not react to messages if false
		else 													$react 	= VarLoad(null, "react_option.php");			//Load saved option file
		if(!CheckFile(null, "vanity_option.php"))				$vanity	= true;											//Allow SFW vanity like hug, nuzzle, kiss
		else 													$vanity = VarLoad(null, "vanity_option.php");			//Load saved option file
		if(!CheckFile(null, "nsfw_option.php"))					$nsfw	= false;										//Allow NSFW commands
		else 													$nsfw 	= VarLoad(null, "nsfw_option.php");				//Load saved option file
		
		/*
		*********************
		*********************
		Load data for author and channel
		*********************
		*********************
		*/
		$author_user													= $message->author; //User object
		$author_channel 												= $message->channel;
		$author_channel_id												= $author_channel->id; 											//echo "author_channel_id: " . $author_channel_id . PHP_EOL;
		$author_channel_class											= get_class($author_channel);
		$is_dm = false;
		if ($author_channel_class === "CharlotteDunois\Yasmin\Models\DMChannel") //True if direct message
			$is_dm = true;
		
		$author_username 												= $author_user->username; 										//echo "author_username: " . $author_username . PHP_EOL;
		$author_discriminator 											= $author_user->discriminator;									//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
		$author_id 														= $author_user->id;												//echo "author_id: " . $author_id . PHP_EOL;
		$author_avatar 													= $author_user->getAvatarURL();									//echo "author_avatar: " . $author_avatar . PHP_EOL;
		$author_check 													= "$author_username#$author_discriminator"; 					//echo "author_check: " . $author_check . PHP_EOL;
		
		echo "Message from $author_check <$author_id> <#$author_channel_id>: {$message_content}", PHP_EOL;
		
		/*
		*********************
		*********************
		Get the guild and guildmember collections for the author
		*********************
		*********************
		*/
		
		if ($is_dm === false){ //Guild message
			$author_guild 												= $author_channel->guild;
			$author_guild_id 											= $author_guild->id; 											//echo "discord_guild_id: " . $author_guild_id . PHP_EOL;
			$author_guild_avatar 										= $author_guild->getIconURL();
			$author_guild_roles 										= $author_guild->roles; 								//Role object for the guild
			if($getverified_channel_id) 	$getverified_channel 		= $author_guild->channels->get($getverified_channel_id);
			if($verify_channel_id) 			$verify_channel 			= $author_guild->channels->get($verify_channel_id);
			if($watch_channel_id) 			$watch_channel 				= $author_guild->channels->get($watch_channel_id);
//			if($welcome_channel_id) 		$welcome_channel			= $author_guild->channels->get($welcome_channel_id);
//			if($introduction_channel_id)	$introduction_channel		= $author_guild->channels->get($introduction_channel
			$author_member 												= $author_guild->members->get($author_id); 				//GuildMember object
			$author_member_roles 										= $author_member->roles; 								//Role object for the author);
		}else{ //Direct message
			if ($author_check != 'Palace Bot#9203'){
				echo "DIRECT MESSAGE - NO PROCESSING OF FUNCTIONS ALLOWED" . PHP_EOL;			
				$dm_text = "DMs not yet supported! Please use commands for this bot within the server.";
				$message->reply("$dm_text \n$server_invite");
			}
			return true;
		}
		
		/*
		*********************
		*********************
		Load persistent variables for author
		*********************
		*********************
		*/
		
		
		
		//CheckDir($folder_name);
		//VarSave($folder_name, "$filename.php", $variable) //Saves variable to a __DIR__\foldername\filename
		//$variable = VarLoad(folder_name, "$filename.php");
		//Use clearstatcache() if deleting a file *needs* to be checked if it exists
		
		$author_folder = $author_id;
		CheckDir($author_folder); //Check if folder exists and create if it doesn't
		if(CheckFile($author_folder, "watchers.php")){
			echo "AUTHOR IS BEING WATCHED" . PHP_EOL;
			$watchers = VarLoad($author_id, "watchers.php");
//			echo "WATCHERS: "; var_dump($watchers); //array of user IDs
			$null_array = true;
			foreach ($watchers as $watcher){
				if ($watcher != NULL){																									//echo "watcher: " . $watcher . PHP_EOL;
					$null_array = false; //mark the array as valid
					try{
//						Get objects for the watcher
						$watcher_member = $author_guild->members->get($watcher);													//echo "watcher_member class: " . get_class($watcher_member) . PHP_EOL;
						$watcher_user = $watcher_member->user;																		//echo "watcher_user class: " . get_class($watcher_user) . PHP_EOL;
						$watcher_user->createDM()->then(function($watcher_dmchannel) use ($message){	//Promise
//							echo "watcher_dmchannel class: " . get_class($watcher_dmchannel) . PHP_EOL; //DMChannel
							return $watcher_dmchannel->send("<@{$message->author->id}> sent a message in <#{$message->channel->id}>: \n{$message_content}");
						});
					}catch(Exception $e){
//						RuntimeException: Unknown property
//						echo 'WATCHER IS IN GUILD' . PHP_EOL;
					}
				}
			}
			if($null_array){ //Delete the null file
				VarDelete($author_id, "watchers.php");
				echo 'AUTHOR IS NO LONGER BEING WATCHED BY ANYONE' . PHP_EOL;
			}
		}
		
		/*
		*********************
		*********************
		Guild-specific variables
		*********************
		*********************
		*/
		
		$creator_name									= "Valithor";;
		$creator_discriminator							= "5947";
		$creator_id										= "116927250145869826";
		$creator_check									= "$creator_name#$creator_discriminator";
		if($author_check != $creator_check) $creator	= false;
		else 								$creator 	= true;
		
		$role_18_name 									= "18+"; 						//Guild 18+ role
		$role_18_id 									= ""; 							//Populated below
		$adult 											= false; 						//Populated below
		
		$role_dev_name 									= "Our Handy Dandy Developer"; 	//Guild Developer role
		$role_dev_id									= ""; 							//Populated below
		$dev											= false; 						//Populated below
		
		$role_owner_name 								= "Emperor Blueberry"; 			//Guild Owner role
		$role_owner_id									= ""; 							//Populated below
		$owner											= false; 						//Populated below
		
		$role_admin_name								= "High Order";					//Guild Administrator role
		$role_admion_id									= ""; 							//Populated below
		$admin 											= false; 						//Populated below
		
		$role_mod_name									= "Palace Guard";				//Guild Moderatir role
		$role_mod_id									= ""; 							//Populated below
		$mod											= false; 						//Populated below
		
		$role_verified_name								= "Floof";						//Guild Verified role
		$role_verified_id								= "659801869396213773"; 		//Populated below
		$verified										= false; 						//Populated below
		
		$role_bot_name									= "Bot";						//Guild Bot role
		$role_bot_id									= ""; 							//Populated below
		$bot											= false; 						//Populated below
		
		$role_vzgbot_name								= "Palace Bot";					//Guild role for this bot (should not change)
		$role_vzgbot_id									= "";							//Populated below
		$vzgbot											= false; 						//Populated below
		
//		$role_verified_name = "Verified";
//		$allowed_verified_roles = [];
		
		$author_guild_roles_names 											= array(); 						//Names of all guild roles
		$author_guild_roles_ids 											= array(); 						//IDs of all guild roles
		foreach ($author_guild_roles as $role){
			$author_guild_roles_names[] 									= $role->name; 												//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
			$author_guild_roles_ids[] 										= $role->id; 												//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
			if ($role->name == $role_18_name) 			$role_18_id			= $role->id;												//echo "role_18_id: " . $role_18_id . PHP_EOL;
			if ($role->name == $role_dev_name)			$role_dev_id 		= $role->id;												//echo "role_dev_name: " . $role_dev_name . PHP_EOL;
			if ($role->name == $role_owner_name)		$role_owner_id	 	= $role->id;												//echo "role_owner_name: " . $role_owner_name . PHP_EOL;
			if ($role->name == $role_admin_name)		$role_admin_id 		= $role->id;												//echo "role_admin_name: " . $role_admin_name . PHP_EOL;
			if ($role->name == $role_mod_name)			$role_mod_id 		= $role->id;												//echo "role_mod_name: " . $role_mod_name . PHP_EOL;
			if ($role->name == $role_verified_name)		$role_verified_id 	= $role->id;												//echo "role_verified_name: " . $role_verified_name . PHP_EOL;
			if ($role->name == $role_bot_name)			$role_bot_id 		= $role->id;												//echo "role_bot_name: " . $role_bot_name . PHP_EOL;
			if ($role->name == $role_vzgbot_name)		$role_vzgbot_id 	= $role->id;												//echo "role_vzgbot_name: " . $role_vzgbot_name . PHP_EOL;
		}																																//echo "discord_guild_roles_names" . PHP_EOL; var_dump($author_guild_roles_names);
																																		//echo "discord_guild_roles_ids" . PHP_EOL; var_dump($author_guild_roles_ids);
		/*
		*********************
		*********************
		Get the guild-related collections for the author
		*********************
		*********************
		*/
//		Populate arrays of the info we need
		$author_member_roles_names 											= array();
		$author_member_roles_ids 											= array();
		$x=0;
		foreach ($author_member_roles as $role){
			if ($x!=0){ //0 is always @everyone so skip it
				$author_member_roles_names[] 								= $role->name; 												//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
				$author_member_roles_ids[]									= $role->id; 												//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
				if ($role->name == $role_18_name)			$adult 			= true;							//Author has the 18+ role
				if ($role->name == $role_dev_name)    		$dev 			= true;							//Author has the dev role
				if ($role->name == $role_owner_name)    	$owner	 		= true;							//Author has the owner role
				if ($role->name == $role_admin_name)		$admin 			= true;							//Author has the admin role
				if ($role->name == $role_mod_name)			$mod 			= true;							//Author has the mod role
				if ($role->name == $role_verified_name)		$verified 		= true;							//Author has the verified role
				if ($role->name == $role_bot_name)			$bot 			= true;							//Author has the bot role
				if ($role->name == $role_vzgbot_name)		$vzgbot 		= true;							//Author is this bot
			}
			$x++;
		}
		if ($creator || $owner)	$bypass = true;
		else					$bypass = false;
		
		/*
		*********************
		*********************
		Help command
		*********************
		*********************
		*/	
		
		if ($message_content == $command_symbol . 'help'){
			$documentation = "**Command symbol: $command_symbol**\n";
			if($creator || $owner){ //toggle options
				$documentation = $documentation . "\n__**Owner:**__\n";
				//react
				$documentation = $documentation . "`react` enables/disables reactions to messages.\n";
				//vanity
				$documentation = $documentation . "`vanity` enables/diables vanity command usage.\n";
				//nsfw
				$documentation = $documentation . "`nsfw` enables/diables nsfw command usage.\n";
				
				//TODO:
				//join
				//leave
			}
			if($creator || $owner || $dev || $admin){
				$documentation = $documentation . "\n__**High Staff:**__\n";
				//v
				$documentation = $documentation . "`v` or `verify` gives the $role_verified_name role to those mentioned.\n";
				//cv
				$documentation = $documentation . "`cv` or `clearv` clears the verification channel and posts a short notice.\n";
				//clearall
				$documentation = $documentation . "`clearall` clears the current channel of up to 100 messages.\n";
				//watch
				$documentation = $documentation . "`watch` sends a direct message to the author whenever the mentioned sends a message.\n";
				//unwatch
				$documentation = $documentation . "`unwatch` removes the effects of the watch command.\n";
				//warn
				$documentation = $documentation . "`warn` logs an infraction.\n";
				//infractions
				$documentation = $documentation . "`infractions` replies with a list of infractions for someone.\n";
				//removeinfraction
				$documentation = $documentation . "`removeinfraction @mention #`\n";
			}
			if($vanity){
				$documentation = $documentation . "\n__**Vanity commands:**__\n";
				//hug/snuggle
				$documentation = $documentation . "`hug` or `snuggle`\n";
				//kiss/smooch
				$documentation = $documentation . "`kiss` or `smooch`\n";
				//nuzzle
				$documentation = $documentation . "`nuzzle`\n";
				//boop
				$documentation = $documentation . "`boop`\n";
			}
			if($nsfw && $adult ){
				//TODO
			}
			//All other functions
			$documentation = $documentation . "\n__**General:**__\n";
			//ping
			$documentation = $documentation . "`ping` replies with 'Pong!'\n";
			//roles / roles @
			$documentation = $documentation . "`roles` displays the roles for the author or user being mentioned.\n";
			//avatar
			$documentation = $documentation . "`avatar` displays the profile picture of the author or user being mentioned.\n";
			$doc_length = strlen($documentation);
			if ($doc_length < 1025){
//				Build the embed message
				$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
				$embed
//					->setTitle("Commands")																	// Set a title
					->setColor("a7c5fd")																	// Set a color (the thing on the left side)
					->setDescription("Commands for Blue's Cloudy Palace")									// Set a description (below title, above fields)
					->addField("⠀", "$documentation")														// New line after this
					
//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
//					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
//				Open a DM channel then send the rich embed message
				$author_user->createDM()->then(function($author_dmchannel) use ($message, $embed){	//Promise
					echo 'SEND ;HELP EMBED' . PHP_EOL;
					return $author_dmchannel->send('', array('embed' => $embed))->done(null, function ($error){
						echo $error.PHP_EOL; //Echo any errors
					});
				});
				return true;
			}else{
				$author_user->createDM()->then(function($author_dmchannel) use ($message, $embed){	//Promise
					echo 'SEND ;HELP MESSAGE' . PHP_EOL;
					$author_dmchannel->send($documentation);
				});
				return true;
			}
		}
		
		/*
		*********************
		*********************
		Creator/Owner option functions
		*********************
		*********************
		*/
		
		if ($creator || $owner)
		if ($message_content_lower == $command_symbol . 'react'){ //toggle reaction functions
//			echo "react: $react" . PHP_EOL;
			if(!CheckFile(null, "react_option.php")){
				VarSave(null, "react_option.php", $react);
//				echo "NEW REACT FILE" . PHP_EOL;
			}
//			VarLoad
			$react_var = VarLoad(null, "react_option.php");
//			echo "react_var: $react_var" . PHP_EOL;
//			VarSave
			$react_flip = !$react_var;
//			echo "react_flip: $react_flip" . PHP_EOL;
			VarSave(null, "react_option.php", $react_flip);
			if ($react_flip === true)
				$message->reply("Reaction functions enabled!");
			else $message->reply("Reaction functions disabled!");
			return true;
		}
		
		if ($creator || $owner)
		if ($message_content_lower == $command_symbol . 'vanity'){ //toggle vanity functions
//			echo "VANITY: $vanity" . PHP_EOL;
			if(!CheckFile(null, "vanity_option.php")){
				VarSave(null, "vanity_option.php", $vanity);
//				echo "NEW VANITY FILE" . PHP_EOL;
			}
//			VarLoad
			$vanity_var = VarLoad(null, "vanity_option.php");
//			echo "vanity_var: $vanity_var" . PHP_EOL;
//			VarSave
			$vanity_flip = !$vanity_var;
//			echo "vanity_flip: $vanity_flip" . PHP_EOL;
			VarSave(null, "vanity_option.php", $vanity_flip);
			if ($vanity_flip === true)
				$message->reply("NSFW functions enabled!");
			else $message->reply("NSFW functions disabled!");
			return true;
		}
		
		if ($creator || $owner)
		if ($message_content_lower == $command_symbol . 'nsfw'){ //toggle nsfw functions
//			echo "nsfw: $nsfw" . PHP_EOL;
			if(!CheckFile(null, "nsfw_option.php")){
				VarSave(null, "nsfw_option.php", $nsfw);
//				echo "NEW NSFW FILE" . PHP_EOL;
			}
//			VarLoad
			$nsfw_var = VarLoad(null, "nsfw_option.php");
//			echo "nsfw_var: $nsfw_var" . PHP_EOL;
//			VarSave
			$nsfw_flip = !$nsfw_var;
//			echo "nsfw_flip: $nsfw_flip" . PHP_EOL;
			VarSave(null, "nsfw_option.php", $nsfw_flip);
			if ($nsfw_flip === true)
				$message->reply("NSFW functions enabled!");
			else $message->reply("NSFW functions disabled!");
			return true;
		}
		
		
		/*
		*********************
		*********************
		Gerneral command functions
		*********************
		*********************
		*/
		
		if ($message_content_lower == $command_symbol . 'ping'){
			$message->reply("Pong!");
			return true;
		}
		
		if ($message_content_lower == $command_symbol . '18+'){
			if ($adult){
				if($react) $message->react("👍");
				$message->reply("You have the 18+ role!");
			}else{
				if($react) $message->react("👎");
				$message->reply("You do NOT have the 18+ role!");
			}
			return true;
		}
		
		if ($message_content_lower == $command_symbol . 'roles'){ //;roles
			echo "GETTING ROLES FOR AUTHOR" . PHP_EOL;
//			Build the string for the reply
			$author_role_name_queue 									= "";
//			$author_role_name_queue_full 								= "Here's a list of roles for you:" . PHP_EOL;
			foreach ($author_guildmember_roles_ids as $author_role){
				$author_role_name_queue 								= "$author_role_name_queue<@&$author_role> ";
			}
			$author_role_name_queue 									= substr($author_role_name_queue, 0, -1);
			$author_role_name_queue_full 								= $author_role_name_queue_full . PHP_EOL . $author_role_name_queue;
//			Send the message
			if($react) $message->react("👍");
//			$message->reply($author_role_name_queue_full . PHP_EOL);
//			Build the embed
			$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
			$embed
//				->setTitle("Roles")																		// Set a title
				->setColor("a7c5fd")																	// Set a color (the thing on the left side)
				->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
				->addField("Roles", 		"$author_role_name_queue_full")								// New line after this if ,true
				
				->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
				->setURL("");                             												// Set the URL
//			Send the message
//			We do not need another promise here, so we call done, because we want to consume the promise
			$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
				echo $error.PHP_EOL; //Echo any errors
			});
			return true; //No more processing, we only want to process the first person mentioned
		}
		
		if (substr($message_content_lower, 0, 7) == $command_symbol . 'roles '){//;roles @
			echo "GETTING ROLES FOR MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			//$mention_role_name_queue_full								= "Here's a list of roles for the requested users:" . PHP_EOL;
			$mention_role_name_queue_default							= "";
//			$mentions_arr_check = (array)$mentions_arr;																					//echo "mentions_arr_check: " . PHP_EOL; var_dump ($mentions_arr_check); //Shows the collection object
//			$mentions_arr_check2 = empty((array) $mentions_arr_check);																	//echo "mentions_arr_check2: " . PHP_EOL; var_dump ($mentions_arr_check2); //Shows the collection object			
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				
				$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
				$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
				
//				Get the roles of the mentioned user
				$target_guildmember 									= $message->guild->members->get($mention_id); 	//This is a GuildMember object
				$target_guildmember_role_collection 					= $target_guildmember->roles;					//This is the Role object for the GuildMember
				
//				Get the avatar URL of the mentioned user
				$target_guildmember_user								= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
				$mention_avatar 										= "{$target_guildmember_user->getAvatarURL()}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);
				
//				Populate arrays of the info we need
//				$target_guildmember_roles_names 						= array();
				$target_guildmember_roles_ids 							= array(); //Not being used here, but might as well grab it
				$x=0;
				foreach ($target_guildmember_role_collection as $role){
					if ($x!=0){ //0 is @everyone so skip it
//						$target_guildmember_roles_names[] 				= $role->name; 													//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
						$target_guildmember_roles_ids[] 				= $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
					}
					$x++;
				}
				
//				Build the string for the reply
//				$mention_role_name_queue 								= "**$mention_id:** ";
				//$mention_role_id_queue 								= "**<@$mention_id>:**\n";
				foreach ($target_guildmember_roles_ids as $mention_role){
//					$mention_role_name_queue 							= "$mention_role_name_queue$mention_role, ";
					$mention_role_id_queue 								= "$mention_role_id_queue<@&$mention_role> ";
				}
				$mention_role_name_queue 								= substr($mention_role_name_queue, 0, -2); 		//Get rid of the extra ", " at the end 
				$mention_role_id_queue 									= substr($mention_role_id_queue, 0, -1); 		//Get rid of the extra ", " at the end 
//				$mention_role_name_queue_full 							= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
				$mention_role_id_queue_full 							= $mention_role_id_queue_full . PHP_EOL . $mention_role_id_queue;
			
//				Check if anyone had their roles changed
//				if ($mention_role_name_queue_default != $mention_role_name_queue){
				if ($mention_role_name_queue_default != $mention_role_id_queue){
//					Send the message
					if($react) $message->react("👍");
					//$message->reply($mention_role_name_queue_full . PHP_EOL);
//					Build the embed
					$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
					$embed
//						->setTitle("Roles")																		// Set a title
						->setColor("a7c5fd")																	// Set a color (the thing on the left side)
						->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
//						->addField("Roles", 	"$mention_role_name_queue_full")								// New line after this
						->addField("Roles", 	"$mention_role_id_queue_full", true)							// New line after this
						
						->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
//						->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
						->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
						->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
						->setURL("");                             												// Set the URL
//					Send the message
//					We do not need another promise here, so we call done, because we want to consume the promise
					$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
						echo $error.PHP_EOL; //Echo any errors
					});
					return true; //No more processing
				}else{
					if($react) $message->react("👎");
					$message->reply("Nobody in the guild was mentioned!");
					return true;  //No more processing
				}
			}
			//Foreach method didn't return, so nobody was mentioned
			$author_channel->send("<@$author_id>, you need to mention someone!");
			return true;
		}
		
		//ymdhis cooldown time
		$avatar_limit['year'] = 0;
		$avatar_limit['month'] = 0;
		$avatar_limit['day'] = 0;
		$avatar_limit['hour'] = 0;
		$avatar_limit['min'] = 10;
		$avatar_limit['sec'] = 0;
		$avatar_limit_seconds = TimeArrayToSeconds($avatar_limit);																		//echo "TimeArrayToSeconds: " . $avatar_limit_seconds . PHP_EOL;
		if ($message_content_lower == $command_symbol . 'avatar'){ //;avatar
			echo "GETTING AVATAR FOR AUTHOR" . PHP_EOL;
//			Check Cooldown Timer
			$cooldown = CheckCooldown($author_id, "avatar_time.php", $avatar_limit);
			if ( ($cooldown[0] == true) || ($bypass) ){
//				Build the embed
				$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
				$embed
//					->setTitle("Avatar")																	// Set a title
					->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//					->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
//					->addField("Total Given", 		"$vanity_give_count")									// New line after this
					
//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
					->setImage("$author_avatar")             												// Set an image (below everything except footer)
					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
				
//				Send the message
//				We do not need another promise here, so we call done, because we want to consume the promise
				$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
					echo $error.PHP_EOL; //Echo any errors
				});
//				Set Cooldown
				SetCooldown($author_id, "avatar_time.php");
				return true;
			}else{
//				Reply with remaining time
				$waittime = $avatar_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using this command again.");
				return true;
			}
		}
		
		if (substr($message_content_lower, 0, 8) == $command_symbol . 'avatar '){//;avatar @
			echo "GETTING AVATAR FOR MENTIONED" . PHP_EOL;
//			Check Cooldown Timer
			$cooldown = CheckCooldown($author_id, "avatar_time.php", $avatar_limit);
			if ( ($cooldown[0] == true) || ($bypass) ){
				$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//					id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
					$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
					
					$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
					$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
					
//					Get the roles of the mentioned user
					$target_guildmember 									= $message->guild->members->get($mention_id); 	//This is a GuildMember object
					$target_guildmember_role_collection 					= $target_guildmember->roles;					//This is the Role object for the GuildMember
					
//					Get the avatar URL of the mentioned user
					$target_guildmember_user								= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
					$mention_avatar 										= "{$target_guildmember_user->getAvatarURL()}";
					
//					Build the embed
					$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
					$embed
//					->setTitle("Avatar")																	// Set a title
					->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//					->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
//					->addField("Total Given", 		"$vanity_give_count")									// New line after this
						
//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
					->setImage("$mention_avatar")             												// Set an image (below everything except footer)
					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
					->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
					
//					Send the message
//					We do not need another promise here, so we call done, because we want to consume the promise
					$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
						echo $error.PHP_EOL; //Echo any errors
					});
					return true;
//					Set Cooldown
					SetCooldown($author_id, "avatar_time.php");
					return true;					
				}
				//Foreach method didn't return, so nobody was mentioned
				$author_channel->send("<@$author_id>, you need to mention someone!");
				return true;
			}else{
//				Reply with remaining time
				$waittime = $avatar_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using this command again.");
				return true;
			}
		}
		
		/*
		*********************
		*********************
		Vanity command functions
		*********************
		*********************
		*/
		if (!$vzgbot)
		if ($vanity){
			//ymdhis cooldown time
			$vanity_limit['year'] = 0;
			$vanity_limit['month'] = 0;
			$vanity_limit['day'] = 0;
			$vanity_limit['hour'] = 0;
			$vanity_limit['min'] = 10;
			$vanity_limit['sec'] = 0;
			$vanity_limit_seconds = TimeArrayToSeconds($vanity_limit);
//			Load author give statistics
			if(!CheckFile($author_id, "vanity_give_count.php"))		$vanity_give_count	= 0;													
			else 													$vanity_give_count	= VarLoad($author_id, "vanity_give_count.php");		
			if(!CheckFile($author_id, "hugger_count.php"))			$hugger_count		= 0;													
			else 													$hugger_count 		= VarLoad($author_id, "hugger_count.php");				
			if(!CheckFile($author_id, "kisser_count.php"))			$kisser_count		= 0;													
			else 													$kisser_count 		= VarLoad($author_id, "kisser_count.php");				
			if(!CheckFile($author_id, "nuzzler_count.php"))			$nuzzler_count		= 0;													
			else 													$nuzzler_count		= VarLoad($author_id, "nuzzler_count.php");			
			if(!CheckFile($author_id, "booper_count.php"))			$booper_count		= 0;													
			else 													$booper_count		= VarLoad($author_id, "booper_count.php");			

			//Load author get statistics
			if(!CheckFile($author_id, "vanity_get_count.php"))		$vanity_get_count	= 0;													
			else 													$vanity_get_count 	= VarLoad($author_id, "vanity_get_count.php");		
			if(!CheckFile($author_id, "hugged_count.php"))			$hugged_count		= 0;													
			else 													$hugged_count 		= VarLoad($author_id, "hugged_count.php");				
			if(!CheckFile($author_id, "kissed_count.php"))			$kissed_count		= 0;													
			else 													$kissed_count 		= VarLoad($author_id, "kissed_count.php");				
			if(!CheckFile($author_id, "nuzzled_count.php"))			$nuzzled_count		= 0;													
			else 													$nuzzled_count		= VarLoad($author_id, "nuzzled_count.php");				
			if(!CheckFile($author_id, "booped_count.php"))			$booped_count		= 0;													
			else 													$booped_count		= VarLoad($author_id, "booped_count.php");				
			
			//Load author receive statistics
			
			if ( (substr($message_content_lower, 0, 5) == $command_symbol . 'hug ') || (substr($message_content_lower, 0, 9) == $command_symbol . 'snuggle ') ){ //;hug ;snuggle
				echo "HUG/SNUGGLE" . PHP_EOL;
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vanity_time.php", $vanity_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Get an array of people mentioned
					$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
					$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
					$mention_role_name_queue_full 								= $mention_role_name_queue_default;
				
					foreach ( $mentions_arr as $mention_param ){
						$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
						$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
						$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
						$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
						
						if ($author_id != $mention_id){
							$hug_messages										= array();
							$hug_messages[]										= "<@$author_id> has given <@$mention_id> a hug! How sweet!";
							$hug_messages[]										= "<@$author_id> saw that <@$mention_id> needed attention, so user gave them a hug!";
							$hug_messages[]										= "<@$author_id> gave <@$mention_id> a hug! Isn't this adorable!";
							$index_selection									= GetRandomArrayIndex($hug_messages);

							//Send the message
							$author_channel->send($hug_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$hugger_count++;
							VarSave($author_id, "hugger_count.php", $hugger_count);
							//Load target get statistics
							if(!CheckFile($mention_id, "vanity_get_count.php"))		$vanity_get_count	= 0;
							else 													$vanity_get_count 	= VarLoad($mention_id, "vanity_get_count.php");
							if(!CheckFile($mention_id, "hugged_count.php"))			$hugged_count		= 0;
							else 													$hugged_count 		= VarLoad($mention_id, "hugged_count.php");
							//Increment get stat counter of target
							$vanity_get_count++;
							VarSave($mention_id, "vanity_get_count.php", $vanity_get_count);
							$hugged_count++;
							VarSave($mention_id, "hugged_count.php", $hugged_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}else{
							$self_hug_messages									= array();
							$self_hug_messages[]								= "<@$author_id> hugs themself. What a wierdo!";
							$index_selection									= GetRandomArrayIndex($self_hug_messages);
							//Send the message
							$author_channel->send($self_hug_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$hugger_count++;
							VarSave($author_id, "hugger_count.php", $hugger_count);
							//Increment get stat counter of author
							$vanity_get_count++;
							VarSave($author_id, "vanity_get_count.php", $vanity_get_count);
							$hugged_count++;
							VarSave($author_id, "hugged_count.php", $hugged_count);
							//Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}
					}
					//foreach method didn't return, so nobody was mentioned
					$author_channel->send("<@$author_id>, you need to mention someone!");
					return true;
				}else{
//				Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return true;
				}
			}
			
			if ( (substr($message_content_lower, 0, 6) == $command_symbol . 'kiss ') || (substr($message_content_lower, 0, 8)) == $command_symbol . 'smooch '){ //;kiss ;smooch
				echo "KISS" . PHP_EOL;
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vanity_time.php", $vanity_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Get an array of people mentioned
					$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
					$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
					$mention_role_name_queue_full 								= $mention_role_name_queue_default;
					
					foreach ( $mentions_arr as $mention_param ){
						$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
						$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
						$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
						$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
						
						if ($author_id != $mention_id){
							$kiss_messages											= array();
							$kiss_messages[]										= "<@$author_id> put their nose to <@$mention_id>’s for a good old smooch! Now that’s cute!";
							$kiss_messages[]										= "<@$mention_id> was surprised when <@$author_id> leaned in and gave them a kiss! Hehe!";
							$kiss_messages[]										= "<@$author_id> has given <@$mention_id> the sweetest kiss on the cheek! Yay!";
							$kiss_messages[]										= "<@$author_id> gives <@$mention_id> a kiss on the snoot.";
							$kiss_messages[]										= "<@$author_id> rubs their snoot on <@$mention_id>, how sweet!";
							$index_selection										= GetRandomArrayIndex($kiss_messages);
							echo "random kiss_message: " . $kiss_messages[$index_selection];
//							Send the message
							$author_channel->send($kiss_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$kisser_count++;
							VarSave($author_id, "kisser_count.php", $kisser_count);
							//Load target get statistics
							if(!CheckFile($mention_id, "vanity_get_count.php"))		$vanity_get_count	= 0;
							else 													$vanity_get_count 	= VarLoad($mention_id, "vanity_get_count.php");
							if(!CheckFile($mention_id, "kissed_count.php"))			$kissed_count		= 0;
							else 													$kissed_count 		= VarLoad($mention_id, "kissed_count.php");
							//Increment get stat counter of target
							$vanity_get_count++;
							VarSave($mention_id, "vanity_get_count.php", $vanity_get_count);
							$kissed_count++;
							VarSave($mention_id, "kissed_count.php", $kissed_count);\
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}else{
							$self_kiss_messages										= array();
							$self_kiss_messages[]									= "<@$author_id> tried to kiss themselves in the mirror. How silly!";
							$index_selection										= GetRandomArrayIndex($self_kiss_messages);
							//Send the message
							$author_channel->send($self_kiss_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$kisser_count++;
							VarSave($author_id, "kisser_count.php", $kisser_count);
							//Increment get stat counter of author
							$vanity_get_count++;
							VarSave($author_id, "vanity_get_count.php", $vanity_get_count);
							$kissed_count++;
							VarSave($author_id, "kissed_count.php", $kissed_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}
					}
					//foreach method didn't return, so nobody was mentioned
					$author_channel->send("<@$author_id>, you need to mention someone!");
					return true;
				}else{
//					Reply with remaining time
					$waittime = $vanity_limit_seconds - $cooldown[1];
					$formattime = FormatTime($waittime);
					$message->reply("You must wait $formattime before using vanity commands again.");
					return true;
				}
			}
			
			if (substr($message_content_lower, 0, 8) == $command_symbol . 'nuzzle ' ){ //;nuzzle @
				echo "NUZZLE" . PHP_EOL;
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vanity_time.php", $vanity_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Get an array of people mentioned
					$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
					$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
					$mention_role_name_queue_full 								= $mention_role_name_queue_default;
				
					foreach ( $mentions_arr as $mention_param ){
						$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
						$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
						$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
						$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
						
						if ($author_id != $mention_id){
							$nuzzle_messages										= array();
							$nuzzle_messages[]										= "<@$author_id> nuzzled into <@$mention_id>’s neck! Sweethearts~ :blue_heart:";
							$nuzzle_messages[]										= "<@$mention_id> was caught off guard when <@$author_id> nuzzled into their chest! How cute!";
							$nuzzle_messages[]										= "<@$author_id> wanted to show <@$mention_id> some more affection, so they nuzzled into <@$mention_id>’s fluff!";
							$nuzzle_messages[]										= "<@$author_id> rubs their snoot softly against <@$mention_id>, look at those cuties!";
							$nuzzle_messages[]										= "<@$author_id> takes their snoot and nuzzles <@$mention_id> cutely.";
							$index_selection										= GetRandomArrayIndex($nuzzle_messages);
//							echo "random nuzzle_messages: " . $nuzzle_messages[$index_selection];
//							Send the message
							$author_channel->send($nuzzle_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$nuzzler_count++;
							VarSave($author_id, "nuzzler_count.php", $nuzzler_count);
							//Load target get statistics
							if(!CheckFile($mention_id, "vanity_get_count.php"))		$vanity_get_count	= 0;
							else 													$vanity_get_count 	= VarLoad($mention_id, "vanity_get_count.php");
							if(!CheckFile($mention_id, "nuzzled_count.php"))		$nuzzled_count		= 0;
							else 													$nuzzled_count 		= VarLoad($mention_id, "nuzzled_count.php");
							//Increment get stat counter of target
							$vanity_get_count++;
							VarSave($mention_id, "vanity_get_count.php", $vanity_get_count);
							$nuzzled_count++;
							VarSave($mention_id, "nuzzled_count.php", $nuzzled_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}else{
							$self_nuzzle_messages									= array();
							$self_nuzzle_messages[]									= "<@$author_id> curled into a ball in an attempt to nuzzle themselves.";
							$index_selection										= GetRandomArrayIndex($self_nuzzle_messages);
//							Send the mssage
							$author_channel->send($self_nuzzle_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$nuzzler_count++;
							VarSave($author_id, "nuzzler_count.php", $nuzzler_count);
							//Increment get stat counter of author
							$vanity_get_count++;
							VarSave($author_id, "vanity_get_count.php", $vanity_get_count);
							$nuzzled_count++;
							VarSave($author_id, "nuzzled_count.php", $nuzzled_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}
					}
					//Foreach method didn't return, so nobody was mentioned
					$author_channel->send("<@$author_id>, you need to mention someone!");
					return true;
				}else{
//					Reply with remaining time
					$waittime = $vanity_limit_seconds - $cooldown[1];
					$formattime = FormatTime($waittime);
					$message->reply("You must wait $formattime before using vanity commands again.");
					return true;
				}
			}
			
			if (substr($message_content_lower, 0, 6) == $command_symbol . 'boop ' ){ //;boop @
				echo "BOOP" . PHP_EOL;
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vanity_time.php", $vanity_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Get an array of people mentioned
					$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
					$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
					$mention_role_name_queue_full 								= $mention_role_name_queue_default;
				
					foreach ( $mentions_arr as $mention_param ){
						$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
						$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
						$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
						$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
						
						if ($author_id != $mention_id){
							$boop_messages										= array();
							
							$boop_messages[]										= "<@$author_id> rubs their snoot softly against <@$mention_id>, look at those cuties!";
							$boop_messages[]										= "<@$author_id> slowly and strategically booped the snoot of <@$mention_id>.";
							$boop_messages[]										= "With a playful smile, <@$author_id> booped <@$mention_id>'s snoot.";
							$index_selection										= GetRandomArrayIndex($boop_messages);
//							echo "random boop_messages: " . $boop_messages[$index_selection];
//							Send the message
							$author_channel->send($boop_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$booper_count++;
							VarSave($author_id, "booper_count.php", $booper_count);
							//Load target get statistics
							if(!CheckFile($mention_id, "vanity_get_count.php"))		$vanity_get_count	= 0;
							else 													$vanity_get_count 	= VarLoad($mention_id, "vanity_get_count.php");
							if(!CheckFile($mention_id, "booped_count.php"))			$booped_count		= 0;
							else 													$booped_count 		= VarLoad($mention_id, "booped_count.php");
							//Increment get stat counter of target
							$vanity_get_count++;
							VarSave($mention_id, "vanity_get_count.php", $vanity_get_count);
							$booped_count++;
							VarSave($mention_id, "booped_count.php", $booped_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing, we only want to process the first person mentioned
						}else{
							$self_boop_messages										= array();
							$self_boop_messages[]									= "<@$author_id> placed a paw on their own nose. How silly!";
							$index_selection										= GetRandomArrayIndex($self_boop_messages);
//							Send the mssage
							$author_channel->send($self_boop_messages[$index_selection]);
							//Increment give stat counter of author
							$vanity_give_count++;
							VarSave($author_id, "vanity_give_count.php", $vanity_give_count);
							$booper_count++;
							VarSave($author_id, "booper_count.php", $booper_count);
							//Increment get stat counter of author
							$vanity_get_count++;
							VarSave($author_id, "vanity_get_count.php", $vanity_get_count);
							$booped_count++;
							VarSave($author_id, "booped_count.php", $booped_count);
//							Set Cooldown
							SetCooldown($author_id, "vanity_time.php");
							return true; //No more processing
						}
					}
					//Foreach method didn't return, so nobody was mentioned
					$author_channel->send("<@$author_id>, you need to mention someone!");
					return true;
				}else{
//					Reply with remaining time
					$waittime = $vanity_limit_seconds - $cooldown[1];
					$formattime = FormatTime($waittime);
					$message->reply("You must wait $formattime before using vanity commands again.");
					return true;
				}
			}
			
			//ymdhis cooldown time
			$vstats_limit['year'] = 0;
			$vstats_limit['month'] = 0;
			$vstats_limit['day'] = 0;
			$vstats_limit['hour'] = 0;
			$vstats_limit['min'] = 30;
			$vstats_limit['sec'] = 0;
			$vstats_limit_seconds = TimeArrayToSeconds($vstats_limit);
			
			if ($message_content_lower == $command_symbol . 'vstats' ){ //;vstats //Give the author their vanity stats as an embedded message
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vstats_limit.php", $vstats_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Build the embed
					$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
					$embed
						->setTitle("Vanity Stats")																// Set a title
						->setColor("a7c5fd")																	// Set a color (the thing on the left side)
						->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
						->addField("Total Given", 		"$vanity_give_count")									// New line after this
						->addField("Hugs", 				"$hugger_count", true)
						->addField("Kisses", 			"$kisser_count", true)
						->addField("Nuzzles", 			"$nuzzler_count", true)
						->addField("Boops", 			"$booper_count", true)
						->addField("⠀", 				"⠀", true)												// Invisible unicode for separator
						->addField("Total Received", 	"$vanity_get_count")									// New line after this
						->addField("Hugs", 				"$hugged_count", true)
						->addField("Kisses", 			"$kissed_count", true)
						->addField("Nuzzles", 			"$nuzzled_count", true)
						->addField("Boops", 			"$booped_count", true)
						
						->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//						->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
						->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
						->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
						->setURL("");                             												// Set the URL
					
//					Send the message
//					We do not need another promise here, so we call done, because we want to consume the promise
					$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
						echo $error.PHP_EOL; //Echo any errors
					});
//					Set Cooldown
					SetCooldown($author_id, "vstats_limit.php");
					return true;
				}else{
//					Reply with remaining time
					$waittime = ($vstats_limit_seconds - $cooldown[1]);
					$formattime = FormatTime($waittime);
					$message->reply("You must wait $formattime before using vstats on yourself again.");
					return true;
				}
			}
			
			if (substr($message_content_lower, 0, 8) == $command_symbol . 'vstats ' ){ //;vstats @
				echo "GETTING VANITY STATS OF MENTIONED" . PHP_EOL;
//				Check Cooldown Timer
				$cooldown = CheckCooldown($author_id, "vstats_limit.php", $vstats_limit);
				if ( ($cooldown[0] == true) || ($bypass) ){
//					Get an array of people mentioned
					$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object			
					foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//						id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
						$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
						$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
						$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
						$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
						$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
						$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
						
//						Get the avatar URL
						$target_guildmember 									= $message->guild->members->get($mention_id); 	//This is a GuildMember object
						$target_guildmember_user								= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
						$mention_avatar 										= "{$target_guildmember_user->getAvatarURL()}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;
						
						
						//Load target get statistics
						if(!CheckFile($mention_id, "vanity_get_count.php"))		$target_vanity_get_count	= 0;
						else 													$target_vanity_get_count 	= VarLoad($mention_id, "vanity_get_count.php");
						if(!CheckFile($mention_id, "vanity_give_count.php"))	$target_vanity_give_count	= 0;
						else 													$target_vanity_give_count 	= VarLoad($mention_id, "vanity_give_count.php");
						if(!CheckFile($mention_id, "hugged_count.php"))			$target_hugged_count		= 0;
						else 													$target_hugged_count 		= VarLoad($mention_id, "hugged_count.php");
						if(!CheckFile($mention_id, "hugger_count.php"))			$target_hugger_count		= 0;
						else 													$target_hugger_count 		= VarLoad($mention_id, "hugger_count.php");
						if(!CheckFile($mention_id, "kissed_count.php"))			$target_kissed_count		= 0;
						else 													$target_kissed_count 		= VarLoad($mention_id, "kissed_count.php");
						if(!CheckFile($mention_id, "kisser_count.php"))			$target_kisser_count		= 0;
						else 													$target_kisser_count 		= VarLoad($mention_id, "kisser_count.php");
						if(!CheckFile($mention_id, "nuzzled_count.php"))		$target_nuzzled_count		= 0;
						else 													$target_nuzzled_count 		= VarLoad($mention_id, "nuzzled_count.php");
						if(!CheckFile($mention_id, "nuzzler_count.php"))		$target_nuzzler_count		= 0;
						else 													$target_nuzzler_count 		= VarLoad($mention_id, "nuzzler_count.php");
						if(!CheckFile($mention_id, "booped_count.php"))			$target_booped_count		= 0;
						else 													$target_booped_count 		= VarLoad($mention_id, "booped_count.php");
						if(!CheckFile($mention_id, "booper_count.php"))			$target_booper_count		= 0;
						else 													$target_booper_count 		= VarLoad($mention_id, "booper_count.php");
						
						//Build the embed
						$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
						$embed
							->setTitle("Vanity Stats")																// Set a title
							->setColor("a7c5fd")																	// Set a color (the thing on the left side)
							->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
							->addField("Total Given", 		"$target_vanity_give_count")							// New line after this
							->addField("Hugs", 				"$target_hugger_count", true)
							->addField("Kisses", 			"$target_kisser_count", true)
							->addField("Nuzzles", 			"$target_nuzzler_count", true)
							->addField("Boops", 			"$target_booper_count", true)
							->addField("⠀", 				"⠀", true)												// Invisible unicode for separator
							->addField("Total Received", 	"$target_vanity_get_count")								// New line after this
							->addField("Hugs", 				"$target_hugged_count", true)
							->addField("Kisses", 			"$target_kissed_count", true)
							->addField("Nuzzles", 			"$target_nuzzled_count", true)
							->addField("Boops", 			"$target_booped_count", true)
							
							->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             		// Set an image (below everything except footer)
							->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
							->setAuthor("$mention_check", "$author_guild_avatar")  // Set an author with icon
							->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
							->setURL("");                             												// Set the URL
						
//						Send the message
//						We do not need another promise here, so we call done, because we want to consume the promise
						$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
							echo $error.PHP_EOL; //Echo any errors
						});
//						Set Cooldown
						SetCooldown($author_id, "vstats_limit.php");
						return true; //No more processing, we only want to process the first person mentioned
					}
					//Foreach method didn't return, so nobody was mentioned
					$author_channel->send("<@$author_id>, you need to mention someone!");
					return true;
				}else{
//					Reply with remaining time
					$waittime = ($vstats_limit_seconds - $cooldown[1]);
					$formattime = FormatTime($waittime);
					$message->reply("You must wait $formattime before using vstats on yourself again.");
					return true;
				}
			}
			
		} //End of vanity commands
		
		/*
		*********************
		*********************
		Restricted command functions
		*********************
		*********************
		*/
		
		if ($creator || $owner) //Only allow these roles to use this
		if ($message_content_lower == $command_symbol . 'genimage'){
			include "imagecreate_include.php"; //Generates $img_output_path
			$image_path = "http://www.valzargaming.com/discord%20-%20palace/" . $img_output_path;
			//echo "image_path: " . $image_path . PHP_EOL;
//			Build the embed message
			$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
			$embed
//				->setTitle("$author_check")																// Set a title
				->setColor("a7c5fd")																	// Set a color (the thing on the left side)
				->setDescription("Blue's Cloudy Palace")									// Set a description (below title, above fields)
//				->addField("⠀", "$documentation")														// New line after this
				
				->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
				->setImage("$image_path")             													// Set an image (below everything except footer)
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
				->setURL("");                             												// Set the URL
//				Open a DM channel then send the rich embed message
			/*
			$author_user->createDM()->then(function($author_dmchannel) use ($message, $embed){	//Promise
				echo 'SEND GENIMAGE EMBED' . PHP_EOL;
				$author_dmchannel->send('', array('embed' => $embed))->done(null, function ($error){
					echo $error.PHP_EOL; //Echo any errors
				});
			});
			*/
			$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
				echo $error.PHP_EOL; //Echo any errors
			});
			return true;
		}
		
		if ($creator) //Only allow these roles to use this
		if ($message_content_lower == $command_symbol . 'processmessages'){	
//			$verify_channel																					//TextChannel				//echo "channel_messages class: " . get_class($verify_channel) . PHP_EOL;
//			$author_messages = $verify_channel->fetchMessages(); 											//Promise
//			echo "author_messages class: " . get_class($author_messages) . PHP_EOL; 						//Promise
			$verify_channel->fetchMessages()->then(function($message_collection) use ($verify_channel){	//Resolve the promise
//				$verify_channel and the new $message_collection can be used here
//				echo "message_collection class: " . get_class($message_collection) . PHP_EOL; 				//Collection messages
				foreach ($message_collection as $message){													//Model/Message				//echo "message_collection message class:" . get_class($message) . PHP_EOL;
//					DO STUFF HERE TO MESSAGES
				}
			});
			return true;
		}
		
		if ($creator) //Only allow these roles to use this
		if ($message_content_lower == $command_symbol . 'restart'){
			echo "RESTARTING BOT" . PHP_EOL;
			$restart_cmd = 'cmd /c "'. __DIR__  . '\run.bat"';
			//echo $restart_cmd . PHP_EOL;
			system($restart_cmd);
			echo 'die' . PHP_EOL;
			die;
		}
		
		if ($creator || $owner || $dev || $admin) //Only allow these roles to use this
		if ( (substr($message_content_lower, 0, 3) == $command_symbol . 'v ') || (substr(($message_content), 0, 8) == $command_symbol . 'verify ') ){ //Verify ;v ;verify
			echo "GIVING VERIFIED ROLE TO MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
			$mention_role_name_queue_full 								= $mention_role_name_queue_default;
			
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				
//				$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
//				$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
				
//				Get the roles of the mentioned user
				echo "mention_id: " . $mention_id . PHP_EOL;
				$target_guildmember 									= $message->guild->members->get($mention_id); 	//This is a GuildMember object
				$target_guildmember_role_collection 					= $target_guildmember->roles;					//This is the Role object for the GuildMember
																																		//echo "target_guildmember_role_collection: " . (count($author_guildmember_role_collection)-1);
				
//				Populate arrays of the info we need
				$target_guildmember_roles_names 						= array();
//				$target_guildmember_roles_ids 							= array(); //Not being used here, but might as well grab it
				$x=0;
				$target_verified = false; //Default
				foreach ($target_guildmember_role_collection as $role){
					if ($x!=0){ //0 is @everyone so skip it
						if ($role->name == $role_verified_name)
							$target_verified 							= true;
						else{
							$target_guildmember_roles_names[] 			= $role->name; 													//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
//							$target_guildmember_roles_ids[] 			= $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
						}
					}
					$x++;
				}
				
				if($target_verified == false){
//					Build the string for the reply
					$mention_role_name_queue 							= "**<@$mention_id>** ";
					$mention_role_name_queue_full 						= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
//					Add the verified role to the member
					$target_guildmember->addRole($role_verified_id)->done(
						function () use ($message) {
							//$message->reply('I have successfully synced your character and updated your roles.');
						},
						function ($error) {
							throw $error;
						}
					);
					echo "role added to $role_verified_id" . PHP_EOL;
				}
			}
//			Send the message
			if ($mention_role_name_queue_default != $mention_role_name_queue_full){
				if($verify_channel){
					if($react) $message->react("👍");
					$verify_channel->send($mention_role_name_queue_full . PHP_EOL);
					return true;
				}else{
					if($react) $message->react("👍");
					$author_channel->send($mention_role_name_queue_full . PHP_EOL);
					return true;
				}
			}else{
				/*
				if($verify_channel){
					$verify_channel->send($mention_role_name_queue_full . PHP_EOL);
					if($react) $message->react("👍");
				}else{
					$author_channel->send($mention_role_name_queue_full . PHP_EOL);
					if($react) $message->react("👍");
				}
				*/
				if($react) $message->react("👎");
				$message->reply("Everyone mentioned already has the verified role!" . PHP_EOL);
				return true;
			}	
		}
		
		if ($creator || $owner || $dev || $admin) //Only allow these roles to use this
		if ( ($message_content_lower == $command_symbol . 'cv') || ( $message_content_lower == $command_symbol . 'clearv') ){ //Clear all messages in the get-verified channel
			echo "CV" . PHP_EOL;
			$getverified_channel->bulkDelete(100);
			$getverified_channel->fetchMessages()->then(function($message_collection) use ($getverified_channel){
				foreach ($message_collection as $message){													//Model/Message				//echo "message_collection message class:" . get_class($message) . PHP_EOL;
					$getverified_channel->message->delete();
				}
			});
			$getverified_channel->send("Welcome to Blue's Cloudly Palace! Please introduce yourself here and one of our staff members will verify you shortly.");
			return true;
		}		
		
		if ($creator || $owner || $dev || $admin) //Only allow these roles to use this
		if ($message_content_lower == $command_symbol . 'clearall'){ //;clearall Clear as many messages in the author's channel at once as possible
			echo "CLEARALL" . PHP_EOL;
			$author_channel->bulkDelete(100);
			return true;
		};
		
		if ($creator || $owner || $dev || $admin) //Only allow these roles to use this
		if (substr($message_content_lower, 0, 7) == $command_symbol . 'watch '){ //;watch @
			echo "SETTING WATCH ON TARGETS MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			if ($watch_channel)	$mention_watch_name_mention_default		= "<@$author_id>";
			$mention_watch_name_queue_default							= $mention_watch_name_mention_default."is watching the following users:" . PHP_EOL;
			$mention_watch_name_queue_full 								= "";
			
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				
//				Place watch info in target's folder
				$watchers[] = VarLoad($mention_id, "$watchers.php");
				$watchers = array_unique($arr);
				$watchers[] = $author_id;
				VarSave($mention_id, "watchers.php", $watchers);
				$mention_watch_name_queue 								= "**<@$mention_id>** ";
				$mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
			}
//			Send a message
			if ($mention_watch_name_queue != ""){
				if ($watch_channel)
				$watch_channel->send($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
				else $message->reply($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
//				React to the original message
//				if($react) $message->react("👀");
				if($react) $message->react("👁");		
				return true;
			}else{
				if($react) $message->react("👎");
				$message->reply("Nobody in the guild was mentioned!");
				return true;
			}
//						
		}
		
		if ($creator || $owner || $dev || $admin) //Only allow these roles to use this
		if (substr($message_content_lower, 0, 9) == $command_symbol . 'unwatch '){ //;unwatch @
			echo "REMOVING WATCH ON TARGETS MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$mention_watch_name_queue_default							= "<@$author_id> is no longer watching the following users:" . PHP_EOL;
			$mention_watch_name_queue_full 								= "";
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				
//				Place watch info in target's folder
				$watchers[] = VarLoad($mention_id, "$watchers.php");
				$watchers = array_value_remove($author_id, $watchers);
				VarSave($mention_id, "watchers.php", $watchers);
				$mention_watch_name_queue 								= "**<@$mention_id>** ";
				$mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
			}
//			React to the original message
			if($react) $message->react("👍");
//			Send the message
			if ($watch_channel){
			$watch_channel->send($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
			}else $author_channel->send($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
			return true;
		}
		
		if ($creator || $owner || $dev || $admin)
		if (substr($message_content_lower, 0, 6) == $command_symbol . 'warn '){ //;warn @
			echo "WARN TARGETS MENTIONED" . PHP_EOL;
			//$message->reply("Not yet implemented!");
//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			if ($warn_channel)	$mention_warn_name_mention_default		= "<@$author_id>";
			$mention_warn_queue_default									= $mention_warn_name_mention_default."warned the following users:" . PHP_EOL;
			$mention_warn_queue_full 									= "";
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
				$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
				
//				Build the string to log
				$filter = "$command_symbol" . "warn <@!$mention_id>";
				$warndate = date("m/d/Y");
				$mention_warn_queue 									= "**$mention_check warned $author_check on $warndate for reason:**" . str_replace($filter, "", $message_content);
				
//				Place warn info in target's folder
				$infractions = VarLoad($mention_id, "infractions.php");
				$infractions[] = $mention_warn_queue;
				VarSave($mention_id, "infractions.php", $infractions);
				$mention_warn_queue_full 								= $mention_warn_queue_full . PHP_EOL . $mention_warn_queue;
			}
//			Send a message
			if ($mention_warn_queue != ""){
				if ($watch_channel)
				$watch_channel->send($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
				else $message->reply($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
//				React to the original message
//				if($react) $message->react("👀");
				if($react) $message->react("👁");		
				return true;
			}else{
				if($react) $message->react("👎");
				$message->reply("Nobody in the guild was mentioned!");
				return true;
			}
		}
		
		if ($creator || $owner || $dev || $admin)
		if (substr($message_content_lower, 0, 13) == $command_symbol . 'infractions '){ //;infractions @
			echo "GET INFRACTIONS FOR TARGET MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 													= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$x = 0;
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
				if ($x == 0){ //We only want the first person mentioned
	//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
					$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
					$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
					$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
					
	//				Place infraction info in target's folder
					$infractions = VarLoad($mention_id, "infractions.php");
					$y = 0;
					foreach ( $infractions as $infraction ){
						//Build a string
						$mention_infraction_queue = $mention_infraction_queue . "$y: " . $infraction . PHP_EOL;
						$y++;
					}
					$mention_infraction_queue_full 								= $mention_infraction_queue_full . PHP_EOL . $mention_infraction_queue;
				}
				$x++;
			}
//			Send a message
			if ($mention_infraction_queue != ""){
				$length = strlen($mention_infraction_queue_full);
				if ($length < 1025){
//				Build the embed message
				$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
				$embed
//					->setTitle("Commands")																	// Set a title
					->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//					->setDescription("Infractions for $mention_check")										// Set a description (below title, above fields)
					->addField("Infractions for $mention_check", "$mention_infraction_queue_full")			// New line after this
//					->addField("⠀", "Use '" . $command_symbol . "removeinfraction @mention #' to remove")	// New line after this
					
//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
//					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
//					Send the embed to the author's channel
					$author_channel->send('', array('embed' => $embed))->done(null, function ($error){
						echo $error.PHP_EOL; //Echo any errors
					});
					return true;
				}else{ //Too long, send reply instead of embed
					$message->reply($mention_infraction_queue_full . PHP_EOL);
//					React to the original message
//					if($react) $message->react("👀");
					if($react) $message->react("🗒️");		
					return true;
				}
			}else{
				if($react) $message->react("👎");
				$message->reply("No log found!");
				return true;
			}
		}
		
		if ($creator || $owner || $dev || $admin)
		if (substr($message_content_lower, 0, 18) == $command_symbol . 'removeinfraction '){ //;infractions @
			echo "GET INFRACTIONS FOR TARGET MENTIONED" . PHP_EOL;
//			Get an array of people mentioned
			$mentions_arr 													= $message->mentions->users; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$x = 0;
			foreach ( $mentions_arr as $mention_param ){																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
				if ($x == 0){ //We only want the first person mentioned
	//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
					$mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					$mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
					$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
					$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
					
	//				Get infraction info in target's folder
					$infractions = VarLoad($mention_id, "infractions.php");
					$proper = $command_symbol."removeinfraction <@!$mention_id> ";
					$strlen = strlen($command_symbol."removeinfraction <@!$mention_id> ");
					$substr = substr($message_content_lower, $strlen);
					
					//Check that message is formatted properly
					if ($proper != substr($message_content_lower, 0, $strlen)){
						$message->reply("Please format your command properly: ;warn @mention number");
						return true;
					}
					
					//Check if $substr is a number
					if ( ($substr != "") && (is_numeric(intval($substr))) ){
						//remove array element and reindex
						//array_splice($infractions, $substr, 1);
						if ($infractions[$substr] != NULL){
							$infractions[$substr] = "Infraction removed by $author_check on " . date("m/d/Y"); // for arrays where key equals offset
							//save the new infraction log
							VarSave($mention_id, "infractions.php", $infractions);
							
							//Send a message
							if($react) $message->react("👍");
							$message->reply("Infraction $substr removed from $mention_check!");
							return true;
						}else{
							if($react) $message->react("👎");
							$message->reply("Infraction '$substr' not found!");
							return true;
						}
						
					}else{
						if($react) $message->react("👎");
						$message->reply("'$substr' is not a number");
						return true;
					}
					
				}
				$x++;
			}
		}
		
	}); //end message function
		
	$discord->on('guildMemberAdd', function ($guildmember){ //Handling of a member joining the guild
		echo "guildMemberAdd" . PHP_EOL;
		//$member is GuildMember class
		//echo "guildmember_class: " . get_class($guildmember) . PHP_EOL;
		$user = $guildmember->user;
		$welcome = true; //Make this work for GuildMember
		
		if($welcome === true){
			$user_username 											= $user->username; 													//echo "author_username: " . $author_username . PHP_EOL;
			$user_id 												= $user->id;														//echo "new_user_id: " . $new_user_id . PHP_EOL;
			$user_discriminator 									= $user->discriminator;												//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
			$user_avatar 											= $user->getAvatarURL();											//echo "author_id: " . $author_id . PHP_EOL;
			$user_check 											= "$user_username#$user_discriminator"; 							//echo "author_check: " . $author_check . PHP_EOL;\
			$user_createdTimestamp									= $user->createdTimestamp;
			$user_createdTimestamp									= date("D M j H:i:s Y", $user_createdTimestamp);
			
			$guild_memberCount										= $guildmember->guild->memberCount;
			
			$welcome_channel_id										= "662042030557364224";				//Channel to send welcome messages to (comment this line to disable)
			$introduction_channel_id								= "609814936721424406";				//Channel where members should introduce themselves
			try{
				if($welcome_channel_id) 			$welcome_channel		= $guildmember->guild->channels->get($welcome_channel_id);
				if($introduction_channel_id) 		$introduction_channel	= $guildmember->guild->channels->get($introduction_channel_id);
				else 								$introduction_channel	= $guildmember->guild->channels->get($welcome_channel_id);
			}catch(Exception $e){
//				RuntimeException: Unknown property
//				echo 'AUTHOR NOT IN GUILD' . PHP_EOL;
			}
			
//			Build the embed
			$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
			$embed
//				->setTitle("$user_check")																			// Set a title
				->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//				->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
				->setDescription("<@$user_id> just joined **Blue's Cloudy Palace**\n
				There are now **$guild_memberCount** members.\n
				Account created on $user_createdTimestamp")												// Set a description (below title, above fields)
				//X days agow
//				->setAuthor("$user_check", "$author_guild_avatar")  									// Set an author with icon
//				->addField("Roles", 		"$author_role_name_queue_full")											// New line after this
				
				->setThumbnail("$user_avatar")														// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				
				->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
				->setURL(""); 
			
//			Making sure the channel exists
			if($welcome_channel){
//				Send the message, welcoming & mentioning the user
				$welcome_channel->send('', array('embed' => $embed))->done(null, function ($error){
					echo $error.PHP_EOL; //Echo any errors
				});
				return true;
			}
		}
		
	}); //end guildMemberAdd function
	
	$discord->on('guildMemberUpdate', function ($member_new, $member_old){ //Handling of a member getting updated
		echo "guildMemberUpdate" . PHP_EOL;
	});
	
	$discord->on('guildMemberRemove', function ($guildmember){ //Handling of a user leaving the guild
		echo "guildMemberRemove" . PHP_EOL;
		//$member is GuildMember class
		//echo "guildmember_class: " . get_class($guildmember) . PHP_EOL;
		$user = $guildmember->user;
		$welcome = true; //Make this work for GuildMember
		
		if($welcome === true){
			$user_username 											= $user->username; 													//echo "author_username: " . $author_username . PHP_EOL;
			$user_id 												= $user->id;														//echo "new_user_id: " . $new_user_id . PHP_EOL;
			$user_discriminator 									= $user->discriminator;												//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
			$user_avatar 											= $user->getAvatarURL();											//echo "author_id: " . $author_id . PHP_EOL;
			$user_check 											= "$user_username#$user_discriminator"; 							//echo "author_check: " . $author_check . PHP_EOL;\
			$user_createdTimestamp									= $user->createdTimestamp;
			$user_createdTimestamp									= date("D M j H:i:s Y", $user_createdTimestamp);
			
			$target_guildmember_role_collection 					= $guildmember->roles;					//This is the Role object for the GuildMember

			$target_guildmember_roles_mentions						= array();
			$x=0;
			foreach ($target_guildmember_role_collection as $role){
				if ($x!=0){ //0 is @everyone so skip it
//					$target_guildmember_roles_names[] 				= $role->name; 													//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
					$target_guildmember_roles_mentions[] 			= "<@&{$role->id}>"; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
				}
				$x++;
			}
			$mention_role_id_queue = "⠀"; //Invisible unicode
			foreach ($target_guildmember_roles_mentions as $mention_role){
//				$mention_role_name_queue 							= "$mention_role_name_queue$mention_role, ";
				$mention_role_id_queue 								= $mention_role_id_queue . "$mention_role";
			}
			
			$guild_memberCount										= $guildmember->guild->memberCount;
			
			$welcome_channel_id										= "662042030557364224";				//Channel to send welcome messages to (comment this line to disable)
			$introduction_channel_id								= "609814936721424406";				//Channel where members should introduce themselves
			try{
				if($welcome_channel_id) 			$welcome_channel		= $guildmember->guild->channels->get($welcome_channel_id);
				if($introduction_channel_id) 		$introduction_channel	= $guildmember->guild->channels->get($introduction_channel_id);
				else 								$introduction_channel	= $guildmember->guild->channels->get($welcome_channel_id);
			}catch(Exception $e){
//				RuntimeException: Unknown property
//				echo 'AUTHOR NOT IN GUILD' . PHP_EOL;
			}
			
//			Build the embed
			$embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
			$embed
//				->setTitle("Leave notification")														// Set a title
				->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//				->setDescription("Blue's Cloudy Palace")												// Set a description (below title, above fields)
				->setDescription("<@$user_id> has left the server!\n
				There are now **$guild_memberCount** members.")											// Set a description (below title, above fields)
//				->setAuthor("$member_check", "$author_guild_avatar")  									// Set an author with icon
				->addField("Roles", 		"$mention_role_id_queue")									// New line after this
				
				->setThumbnail("$user_avatar")															// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')            // Set an image (below everything except footer)
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				
				->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
				->setURL("");                             												// Set the URL
			
//			Making sure the channel exists
			if($welcome_channel){
				echo "Welcome channel found!";
//				Send the message, announcing the member's departure
/*
				$welcome_channel->send("<@$new_member_id> has left Blue's Cloudy Palace.")
					->otherwise(function ($error){
						echo $error.PHP_EOL;
					});
*/
//				Send the message
//				We do not need another promise here, so we call done, because we want to consume the promise
				$welcome_channel->send('', array('embed' => $embed))->done(null, function ($error){
					echo $error.PHP_EOL; //Echo any errors
				});
				return true;
			}else{
				echo "No welcome channel!";
				return true;
			}
		}
	}); //end GuildMemberRemove function
		
	$discord->on('guildBanAdd', function ($guild, $user){ //Handling of a user getting banned
		echo "guildBanAdd" . PHP_EOL;
		//
	});
	
	$discord->on('guildBanRemove', function ($guild, $user){ //Handling of a user getting unbanned
		echo "guildBanRemove" . PHP_EOL;
		//
	});
	
	$discord->on('messageUpdate', function ($message_new, $message_old){ //Handling of a message being changed
		$message_content_new = $message_new->content;
		$message_content_old = $message_old->content;
		echo "messageUpdate" . PHP_EOL;
		//
	});
	
	$discord->on('messageDelete', function ($message){ //Handling of a message being deleted
		$message_content = $message->content;
		echo "messageDelete" . PHP_EOL;
		//
	});
	
	$discord->on('messageDeleteBulk', function ($messages){ //Handling of multiple messages being deleted
		echo "messageDeleteBulk" . PHP_EOL;
		//
	});
	
	$discord->on('messageReactionAdd', function ($reaction, $user){ //Handling of a message being reacted to
		echo "messageReactionAdd" . PHP_EOL;
		//
	});
	
	$discord->on('messageReactionRemove', function ($reaction, $user){ //Handling of a message reaction being removed
		echo "messageReactionRemove" . PHP_EOL;
		//
	});
	
	$discord->on('messageReactionRemoveAll', function ($message){ //Handling of all reactions being removed from a message
		$message_content = $message->content;
		echo "messageReactionRemoveAll" . PHP_EOL;
		//
	});
	
	$discord->on('channelCreate', function ($channel){ //Handling of a channel being created
		echo "channelCreate" . PHP_EOL;
		//
	});
	
	$discord->on('channelDelete', function ($channel){ //Handling of a channel being deleted
		echo "channelDelete" . PHP_EOL;
		//
	});
	
	$discord->on('channelUpdate', function ($channel){ //Handling of a channel being changed
		echo "channelUpdate" . PHP_EOL;
		//
	});
		
	$discord->on('userUpdate', function ($user_new, $user_old){ //Handling of a user changing their username/avatar/etc
		echo "userUpdate" . PHP_EOL;
		//
	});
		
	$discord->on('roleCreate', function ($role){ //Handling of a role being created
		echo "roleCreate" . PHP_EOL;
		//
	});
	
	$discord->on('roleDelete', function ($role){ //Handling of a role being deleted
		echo "roleDelete" . PHP_EOL;
		//
	});
	
	$discord->on('roleUpdate', function ($role_new, $role_old){ //Handling of a role being changed
		echo "roleUpdate" . PHP_EOL;
		//
	});
	
	$discord->on('voiceStateUpdate', function ($member_new, $member_old){ //Handling of a member's voice state changing (leaves/joins/etc.)
		echo "voiceStateUpdate" . PHP_EOL;
		//
	});
	
	$discord->on('error', function ($error){ //Handling of thrown errors
		echo "ERROR" . PHP_EOL;
		echo $error . PHP_EOL;
		return true;
	});
}); //end main function ready

require 'token.php'; //Token for the bot
$discord->login($token)->done();
$loop->run();
?>