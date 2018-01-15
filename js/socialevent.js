var socialEventApp = angular.module('socialEventApp', ['ngRoute', 'ngCookies']);

var EVT_SHOW_ERROR = "SHOW_ERROR";
var EVT_SHOW_OK = "SHOW_OK";
var EVT_SHOW_DIALOG = "SHOW_DIALOG";

socialEventApp.config(function($routeProvider, $httpProvider) {
	$routeProvider.when('/hello', {
		templateUrl: 'partial/hello.html',
		controller: 'helloController'
	}).when('/offers', {
		templateUrl: 'partial/offers.html',
		controller: 'offersController'
	}).when('/events', {
		templateUrl: 'partial/events.html',
		controller: 'eventsController'
	}).when('/activities', {
		templateUrl: 'partial/activities.html',
		controller: 'activitiesController'
	}).when('/myoffers', {
		templateUrl: 'partial/myOffers.html',
		controller: 'myoffersController'
	}).when('/myevents', {
		templateUrl: 'partial/myEvents.html',
		controller: 'myeventsController'
	}).when('/myactivities', {
		templateUrl: 'partial/myActivities.html',
		controller: 'myactivitiesController'
	}).when('/myparticipations', {
		templateUrl: 'partial/myParticipations.html',
		controller: 'myparticipationsController'
	}).otherwise('/hello'); // Weiterleitung auf '/hello', wenn ansonsten kein passender Eintrag existiert

	$httpProvider.defaults.headers.common["X-Requested-With"] = 'XMLHttpRequest';
});

socialEventApp.filter('nl2br', function($sce){
    return function(msg) { 
        var msg = (msg + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
        return $sce.trustAsHtml(msg);
    }
});

socialEventApp.directive('customOnChange', function() {
 return {
	 restrict: 'A',
	 link: function (scope, element, attrs) {
		 var onChangeFunc = scope.$eval(attrs.customOnChange);
		 element.bind('change', onChangeFunc);
	}	
 };
});

socialEventApp.service('HelperService', function($rootScope, $window, $timeout, $cookies, $location, $http){
	var element;
	this.setFocus = function (){
		$window.document.getElementById(element).focus();
	}
	this.updateFocus = function(elementId) {
		element = elementId;
		$timeout(this.setFocus, 10);
	}
	
	this.sendToServer = function (httpmethod, url, data, successCallback){
	if (!$rootScope.sessionkey) $rootScope.sessionkey = $cookies.get('sessionkey');
	data.sessionkey = $rootScope.sessionkey;
	
	var req = {
		  method: httpmethod,
		  url: url
	}
	
	switch (httpmethod.toLowerCase()){
		case "get":
		case "delete":
		case "head":
		case "jsonp":
			req.params = data;
			break;
		case "post":
		case "put":
		case "patch":
			req.data = data;
			break;
	}
	
	$http(req).then(successCallback, function(response){
			if (response.status == 401){
				$cookies.remove('sessionkey');
				$rootScope.sessionkey = "";
				if ($location.path()!="/hello" && $location.path()!="/"){
					$rootScope.$emit(EVT_SHOW_ERROR, "Sie müssen sich anmelden um, auf diese Seite zuzugreifen.");
				}
			} else if (!response.data.result && response.status == 403){
				$rootScope.$emit(EVT_SHOW_ERROR, "Für die gewünschte Seite haben Sie keine Berechtigungen.");
			} else {
				if (response.data && response.data.result){
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
					return;
				}
				$rootScope.$emit(EVT_SHOW_ERROR, "Es ist ein interner Fehler aufgetreten. ("+response.status+" "+response.statusText+")");
			}
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
			$location.path("/").search({});
		});
	}
	
	this.checkSession = function(){
		if (!$rootScope.sessionkey) $rootScope.sessionkey = $cookies.get('sessionkey');
		var emptyPage = ($location.path()=="")
		var helloPage = ($location.path()=="/hello");
		
		if (!emptyPage && !helloPage){
			$rootScope.nextPage = $location.url();
		}
		
		if (!helloPage && (!$rootScope.sessionkey)){
			if (!emptyPage){
				$rootScope.$emit(EVT_SHOW_ERROR, "Sie müssen sich anmelden, um auf diese Seite zuzugreifen.");
				$rootScope.loggedIn = false;
			}
			$rootScope.loggedIn = false;
			$location.path( "/hello" ).search({});
		} else if (!helloPage && $rootScope.sessionkey) {
			this.sendToServer('get','api/sessions/'+$rootScope.sessionkey,{},function(response){
				if (response.data.result =="success"){
					$rootScope.loggedIn = true;
					$rootScope.role=response.data.role;
					$rootScope.name=response.data.mail;
					if ($rootScope.nextPage){
						$location.url($rootScope.nextPage);
					} else {
						$location.path("/activities").search({});
					}
					$rootScope.nextPage = "";
				} else {
					$rootScope.sessionkey = "";
					$cookies.remove('sessionkey');
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
					$location.path( "/hello" );		
				}
			});			
		} else if (helloPage && $rootScope.sessionkey){
			if ($rootScope.nextPage){
				$location.url($rootScope.nextPage);
			} else {
				$location.path("/activities").search({});
			}
		}
	}
});

socialEventApp.controller('overlayController', function($scope, $rootScope, $timeout) {
	var showMessage = function (messageText){
		$scope.displayedMessage = messageText;
		$scope.closeMessageTimer = $timeout($scope.closeMessage, 3000);		
	}
	$scope.closeMessage = function (){
		$timeout.cancel($scope.closeMessageTimer);
		$scope.displayedMessage="";
	}
	$rootScope.$on(EVT_SHOW_ERROR, function(event, args) {
		$scope.displayErrorMessage=true;
		showMessage(args);
	});
	$rootScope.$on(EVT_SHOW_OK, function(event, args) {
		$scope.displayErrorMessage=false;
		showMessage(args);
	});
	$rootScope.$on(EVT_SHOW_DIALOG, function(event, args) {
		$scope.dialogInclude = args;
	});
});
socialEventApp.controller('titlebarController', function($scope, $rootScope, $cookies, $location, HelperService) {
	$scope.loginClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/login.html");
	};
	$scope.settingsClicked = function(){
		if ($rootScope.role == 1){
			$rootScope.$emit(EVT_SHOW_DIALOG, "partial/settingsUser.html");
		} else if ($rootScope.role == 2) {
			$rootScope.$emit(EVT_SHOW_DIALOG, "partial/settingsHost.html");
		} else {
			$rootScope.$emit(EVT_SHOW_ERROR, "Es ist ein Fehler aufgetreten!");
		}			
	};
	$scope.logoutClicked = function(){
		HelperService.sendToServer('delete','api/sessions/'+$rootScope.sessionkey,{},function(response){
			$cookies.remove('sessionkey');
			$rootScope.sessionkey = "";
			$rootScope.loggedIn = false;
			$location.path( "/hello" ).search({});
			if(response.data.result =="success"){
				$rootScope.$emit(EVT_SHOW_OK, "Logout erfolgreich.");
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
});

socialEventApp.controller('helloController', function($scope, $rootScope, $timeout, HelperService) {
	HelperService.checkSession();
	$scope.registerUserClicked = function(){
		$rootScope.role = 1;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/registerUser.html");
	};
	$scope.registerHostClicked = function(){
		$rootScope.role = 2;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/registerHost.html");
	};
});

socialEventApp.controller('loginController', function($scope, $http, $rootScope, $cookies, $location, $window, $timeout, HelperService){
	$scope.loginClicked = function() {
		$window.document.activeElement.blur();
		$http.post('/api/sessions',$scope.logindata).then(function(response){
			if(response.data.result =="success"){
				$rootScope.$emit(EVT_SHOW_OK, "Login erfolgreich.");
				var expireDate = new Date();
				expireDate.setDate(expireDate.getDate() + 365); // Anmeldung gilt für 1 Jahr
				$cookies.put('sessionkey',response.data.sessionkey,{secure: true, expires: expireDate});
				$rootScope.sessionkey = response.data.sessionkey;
				$location.path( "/activities" ).search({});
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				$rootScope.sessionkey = "";
				$cookies.remove('sessionkey');
			}
		}, function(response) {
			if (!response.data.errormessage){
				$rootScope.$emit(EVT_SHOW_ERROR, "Bei der Bearbeitung der Anfrage ist ein Fehler aufgetreten.");
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}
	$scope.abortClicked = function() {
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	}

	HelperService.updateFocus('username');
});
socialEventApp.controller('registerController', function($scope, $http, $rootScope, $cookies, $location, $window, $timeout, HelperService){
	$scope.deleteClicked = function() {
		$scope.profilePicture = "img/blank_profile.png";
	}
	$scope.loginClicked = function() {
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/login.html");
	}
	$scope.abortClicked = function() {
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	}
	$scope.registerClicked = function() {
		$window.document.activeElement.blur();
		if(typeof $scope.registerdata == "undefined")
			$scope.registerdata = {};
		$scope.registerdata.picture = $scope.profilePicture;
		$scope.registerdata.role = $rootScope.role;
		if ($scope.registerdata.pw1 != $scope.registerdata.pw2){
			HelperService.updateFocus('pw2');
			$rootScope.$emit(EVT_SHOW_ERROR, "Die eingegebenen Passwörter stimmen nicht überrein.");
		} else {
			HelperService.sendToServer('post','api/users',$scope.registerdata,function(response){
			if(response.data.result =="success"){
				$rootScope.$emit(EVT_SHOW_OK, "Das Konto wurde erfolgreich erstellt. Es kann jetzt verwendet werden.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "partial/login.html");
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});			
		}
	}
	
	$scope.onPictureUpdate = function(){
		var f = $window.document.getElementById('file').files[0];
		if (f.type.toLowerCase() != 'image/png' && f.type.toLowerCase() != 'image/jpeg'){
			$rootScope.$emit(EVT_SHOW_ERROR, "Ausgewählter Dateityp ("+f.type+") nicht unterstützt. Nur *.jpg und *.png erlaubt.");
		} else {
			new ImageCompressor(f, {
				quality: 0.6,
				maxHeight: 100,
				success(result) {
					r = new FileReader();
					r.onloadend = function(e){
					$scope.profilePicture = 'data:'+f.type+';base64,' + btoa(e.target.result);
					$scope.$apply();
					}
					r.readAsBinaryString(result);
				},
				error(e) {
				  $rootScope.$emit(EVT_SHOW_ERROR, e.message);
				}
			});
		}
		$scope.$apply();
	}
	$scope.profilePicture = "img/blank_profile.png";
	HelperService.updateFocus('name');
});
socialEventApp.controller('settingsController', function($scope, $http, $rootScope, $cookies, $location, $timeout, $window, HelperService){
	HelperService.checkSession();
	$scope.abortClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.saveClicked = function(){
		var patchData = {};
		if ($scope.changepw && $scope.changepw.oldpw){
			if ($scope.changepw.pw1 != $scope.changepw.pw2){
				HelperService.updateFocus('pw2');
				$rootScope.$emit(EVT_SHOW_ERROR, "Die eingegebenen Passwörter stimmen nicht überrein.");
				return;
			} else {
				patchData.oldpw = $scope.changepw.oldpw;
				patchData.newpw = $scope.changepw.pw1;		
			}
		}
		if ($rootScope.role == 1){
			if ($scope.profilePictureChanged){
				patchData.profilepicture = $scope.profilePicture;
			}
		} else if ($rootScope.role == 2){
			if ($scope.companyinfo_org != $scope.companyinfo){
				patchData.companyinfo = $scope.companyinfo;
			}
			if ($scope.companyPictureChanged){
				patchData.companypictures = btoa(JSON.stringify($scope.companyPictures));
			}
		}
		if (Object.keys(patchData).length>0){
			HelperService.sendToServer('patch','api/users/'+$rootScope.name,patchData,function(response){
				if(response.data.result == "success"){
					$rootScope.$emit(EVT_SHOW_OK, "Die Änderungen wurden übernommen.");
					$rootScope.$emit(EVT_SHOW_DIALOG, "");
					$route.reload();
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		} else {
			$rootScope.$emit(EVT_SHOW_ERROR, "Es wurden keine Änderungen erkannt.");
		}
	};
	$scope.deleteButtonClicked = function() {
		if ($scope.profilePicture != "img/blank_profile.png")
			$scope.profilePictureChanged = true;
		$scope.profilePicture = "img/blank_profile.png";
	}
	$scope.deleteClicked = function(img) {
		$scope.companyPictureChanged = true;
		var i = $scope.companyPictures.indexOf(img);
		if(i != -1) {
			$scope.companyPictures.splice(i, 1);
		}
	}
	$scope.onPictureUpdate = function(){
		var f = $window.document.getElementById('file').files[0];
		if (f.type.toLowerCase() != 'image/png' && f.type.toLowerCase() != 'image/jpeg'){
			$rootScope.$emit(EVT_SHOW_ERROR, "Ausgewählter Dateityp ("+f.type+") nicht unterstützt. Nur *.jpg und *.png erlaubt.");
		} else {
			new ImageCompressor(f, {
				quality: 0.6,
				maxHeight: 100,
				success(result) {
					r = new FileReader();
					r.onloadend = function(e){
						if ($rootScope.role == 1){
							$scope.profilePicture = 'data:'+f.type+';base64,' + btoa(e.target.result);
							$scope.profilePictureChanged = true;
						} else {
							if ($scope.companyPictures.length + 1 > 5){
								$rootScope.$emit(EVT_SHOW_ERROR, "Die maximale Anzahl an Bildern wurde erreicht!");
							} else if ($scope.companyPictures.indexOf('data:'+f.type+';base64,' + btoa(e.target.result)) != -1){
								$rootScope.$emit(EVT_SHOW_ERROR, "Das Bild ist bereits vorhanden!");
							} else {
								$scope.companyPictures.push('data:'+f.type+';base64,' + btoa(e.target.result));
								$scope.companyPictureChanged = true;
							}
						}
					$scope.$apply();
					}
					r.readAsBinaryString(result);
				},
				error(e) {
				  $rootScope.$emit(EVT_SHOW_ERROR, e.message);
				},
			  });
		}
		$scope.$apply();
	}
	
	$scope.profilePicture = "img/blank_profile.png";
	$scope.companyinfo = "Wird geladen...";
	$scope.companyPictures = [];
	HelperService.sendToServer('get','api/users/'+$rootScope.name,{},function(response){
		if(response.data.result =="success"){
			$scope.profilePicture = response.data.profile.profilepicture;
			$scope.companyinfo = response.data.profile.companyinfo;
			$scope.companyinfo_org = $scope.companyinfo;
			if (response.data.profile.companypictures){
				$scope.companyPictures = JSON.parse(atob(response.data.profile.companypictures));
			}
		}
	});
			
	HelperService.updateFocus('oldpw');
});

socialEventApp.controller('offersController', function($scope, $http, $rootScope, $cookies, $location, HelperService){
	HelperService.checkSession();
	
	$scope.showOfferClicked = function(offer) {
		$rootScope.eventObject=offer;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewOffer.html");
	}
	
	$scope.editOfferClicked = function(offer) {
		$rootScope.eventObject = offer;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyOffer.html");
	}
	
	$scope.showActivitiesClicked = function(offer) {
		if (offer.activityCount == 0){
			$rootScope.$emit(EVT_SHOW_ERROR, "Bisher wurden hierfür keine Aktivitäten erstellt.");
		} else {
			$location.path( "/activities" ).search({offerid: offer.offerid});
		}
	}
	
	$scope.createActivityClicked = function(offer) {
		$rootScope.eventObject = offer;
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	}
	
	// -> Fisher–Yates shuffle algorithm
	var shuffleArray = function(array) {
	  var m = array.length, t, i;
	  while (m) {
		i = Math.floor(Math.random() * m--);
		t = array[m];
		array[m] = array[i];
		array[i] = t;
	  }
	  return array;
	}
	
	var getOffers = function (){
		$scope.loading = true;
		$scope.offers = [];
		HelperService.sendToServer('get','api/offers',{},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.offers = shuffleArray(response.data.offers);

				for (var i = 0; i < $scope.offers.length; i++) {
					$scope.offers[i].offerPictures = JSON.parse(atob($scope.offers[i].pictures));
					if ($scope.offers[i].offerPictures.length == 0){
						$scope.offers[i].offerPictures.push("img/noPicture.png");
					}
				}
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getOffers();

});
socialEventApp.controller('myoffersController', function($scope, $http, $rootScope, $cookies, $location, HelperService){
	HelperService.checkSession();
	$scope.createOfferClicked = function(){
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyOffer.html");
	};
	$scope.viewOfferClicked = function(offer){
		$rootScope.eventObject = offer;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewOffer.html");
	};
	$scope.editOfferClicked = function(offer){
		$rootScope.eventObject = offer;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyOffer.html");
	};
	$scope.deleteOfferClicked = function(offer){
		$rootScope.eventObject = offer;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteOffer.html");
	};
	var getMyOffers = function (){
		$scope.loading = true;
		$scope.myOffers = [];
		HelperService.sendToServer('get','api/offers',{profile:$rootScope.name},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.myOffers = response.data.offers;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getMyOffers();
});
socialEventApp.controller('modifyOfferController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.abortClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.deleteClicked = function(img) {
		var i = $scope.offerPictures.indexOf(img);
		if(i != -1) {
			$scope.offerPictures.splice(i, 1);
			$scope.changed=true;
		}
	};
	$scope.onPictureUpdate = function(){
		var f = $window.document.getElementById('file').files[0];
		if (f.type.toLowerCase() != 'image/png' && f.type.toLowerCase() != 'image/jpeg'){
			$rootScope.$emit(EVT_SHOW_ERROR, "Ausgewählter Dateityp ("+f.type+") nicht unterstützt. Nur *.jpg und *.png erlaubt.");
		} else {
			new ImageCompressor(f, {
				quality: 0.6,
				maxHeight: 440,
				success(result) {
					r = new FileReader();
					r.onloadend = function(e){
						if ($scope.offerPictures.length + 1 > 5){
							$rootScope.$emit(EVT_SHOW_ERROR, "Die maximale Anzahl an Bildern wurde erreicht!");
						} else if ($scope.offerPictures.indexOf('data:'+f.type+';base64,' + btoa(e.target.result)) != -1){
							$rootScope.$emit(EVT_SHOW_ERROR, "Das Bild ist bereits vorhanden!");
						} else {
							$scope.offerPictures.push('data:'+f.type+';base64,' + btoa(e.target.result));
							$scope.changed=true;
						}
						$scope.$apply();
					}
					r.readAsBinaryString(result);
				},
				error(e) {
				  $rootScope.$emit(EVT_SHOW_ERROR, e.message);
				},
			});
		}			
		$scope.$apply();
	};
	
	$scope.createClicked = function() {
		$scope.modifyOffer.offerpictures = btoa(JSON.stringify($scope.offerPictures));
		HelperService.sendToServer('post','api/offers',$scope.modifyOffer,function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Das Dauerangebot wurde erfolgreich erstellt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	$scope.editClicked = function() {
		if ($scope.changed){
			$scope.modifyOffer.offerpictures = btoa(JSON.stringify($scope.offerPictures));
			HelperService.sendToServer('put','api/offers/'+$scope.modifyOffer.offerid,$scope.modifyOffer,function(response){
				if(response.data.result == "success"){
					$rootScope.$emit(EVT_SHOW_OK, "Das Dauerangebot wurde erfolgreich geändert.");
					$rootScope.$emit(EVT_SHOW_DIALOG, "");
					$route.reload();
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		} else {
			$rootScope.$emit(EVT_SHOW_OK, "Keine Änderung erkannt.");
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
		};
	};
	$scope.changed = false;
	if ($rootScope.actionType == 'create'){
		$scope.modifyOffer = {};
		$scope.offerPictures = [];
	} else {
		$scope.modifyOffer = Object.assign({},$rootScope.eventObject);
		$scope.offerPictures = JSON.parse(atob($scope.modifyOffer.pictures));
	}
	HelperService.updateFocus('title');
});
socialEventApp.controller('deleteOfferController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.noClicked = function(offerid){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.yesClicked = function() {
		HelperService.sendToServer('delete','api/offers/'+$scope.deleteOffer.offerid,{},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Das Dauerangebot wurde erfolgreich entfernt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
	$scope.deleteOffer = Object.assign({},$rootScope.eventObject);
});
socialEventApp.controller('viewOfferController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.backClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.editClicked = function(offerid){
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyOffer.html");
	};
	$scope.deleteClicked = function(offerid){
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteOffer.html");
	};
	$scope.showActivitiesClicked = function() {
		if ($scope.viewOffer.activityCount == 0){
			$rootScope.$emit(EVT_SHOW_ERROR, "Bisher wurden hierfür keine Aktivitäten erstellt.");
		} else {
			$location.path( "/activities" ).search({offerid: $scope.viewOffer.offerid});
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
		}
	}
	$scope.createActivityClicked = function() {
		$rootScope.eventObject = $scope.viewOffer;
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	}
	$scope.viewOffer = $rootScope.eventObject;
	$scope.offerPictures = JSON.parse(atob($scope.viewOffer.pictures));
	if ($scope.offerPictures.length == 0){
		$scope.offerPictures.push("img/noPicture.png");
	}
	HelperService.sendToServer('get','api/users/'+$scope.viewOffer.provider,{},function(response){
			if(response.data.result =="success"){
				$scope.viewOffer.providerDetails = response.data.profile;
			}
	});
});

socialEventApp.controller('eventsController', function($scope, $http, $rootScope, $cookies, $location, HelperService){
	HelperService.checkSession();
	
	$scope.showEventClicked = function(event) {
		$rootScope.eventObject=event;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewEvent.html");
	}
	
	$scope.editEventClicked = function(event) {
		$rootScope.eventObject = event;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyEvent.html");
	}
	
	$scope.showActivitiesClicked = function(event) {
		if (event.activityCount == 0){
			$rootScope.$emit(EVT_SHOW_ERROR, "Bisher wurden hierfür keine Aktivitäten erstellt.");
		} else {
			$location.path( "/activities" ).search({eventid: event.eventid});
		}
	}
	
	$scope.createActivityClicked = function(event) {
		$rootScope.eventObject = event;
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	}
	
	var getEvents = function (){
		$scope.loading = true;
		$scope.events = [];
		HelperService.sendToServer('get','api/events',{},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.events = response.data.events;
				for (var i = 0; i < $scope.events.length; i++) {
					$scope.events[i].eventPictures = JSON.parse(atob($scope.events[i].pictures));
					if ($scope.events[i].eventPictures.length == 0){
						$scope.events[i].eventPictures.push("img/noPicture.png");
					}
				}
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getEvents();

});
socialEventApp.controller('myeventsController', function($scope, $http, $rootScope, $cookies, $location, HelperService){
	HelperService.checkSession();
	$scope.createEventClicked = function(){
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyEvent.html");
	};
	$scope.viewEventClicked = function(event){
		$rootScope.eventObject = event;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewEvent.html");
	};
	$scope.editEventClicked = function(event){
		$rootScope.eventObject = event;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyEvent.html");
	};
	$scope.deleteEventClicked = function(event){
		$rootScope.eventObject = event;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteEvent.html");
	};
	var getMyEvents = function (){
		$scope.loading = true;
		$scope.myEvent = [];
		HelperService.sendToServer('get','api/events',{profile:$rootScope.name},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.myEvents = response.data.events;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getMyEvents();
});
socialEventApp.controller('modifyEventController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.abortClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.deleteClicked = function(img) {
		var i = $scope.eventPictures.indexOf(img);
		if(i != -1) {
			$scope.eventPictures.splice(i, 1);
			$scope.changed=true;
		}
	};
	$scope.onPictureUpdate = function(){
		var f = $window.document.getElementById('file').files[0];
		if (f.type.toLowerCase() != 'image/png' && f.type.toLowerCase() != 'image/jpeg'){
			$rootScope.$emit(EVT_SHOW_ERROR, "Ausgewählter Dateityp ("+f.type+") nicht unterstützt. Nur *.jpg und *.png erlaubt.");
		} else {
			new ImageCompressor(f, {
				quality: 0.6,
				maxHeight: 440,
				success(result) {
					r = new FileReader();
					r.onloadend = function(e){
					if ($scope.eventPictures.length + 1 > 5){
						$rootScope.$emit(EVT_SHOW_ERROR, "Die maximale Anzahl an Bildern wurde erreicht!");
					} else if ($scope.eventPictures.indexOf('data:'+f.type+';base64,' + btoa(e.target.result)) != -1){
						$rootScope.$emit(EVT_SHOW_ERROR, "Das Bild ist bereits vorhanden!");
					} else {
						$scope.eventPictures.push('data:'+f.type+';base64,' + btoa(e.target.result));
						$scope.changed=true;
					}
					$scope.$apply();
					}
					r.readAsBinaryString(result);
				},
				error(e) {
				  $rootScope.$emit(EVT_SHOW_ERROR, e.message);
				},
			  });
		}
	$scope.$apply();
	}
	
	$scope.createClicked = function() {
		$scope.modifyEvent.eventPictures = btoa(JSON.stringify($scope.eventPictures));
		$scope.modifyEvent.start = $window.document.getElementById("start").value;
		$scope.modifyEvent.end = $window.document.getElementById("end").value;
		HelperService.sendToServer('post','api/events',$scope.modifyEvent,function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Der Event wurde erfolgreich erstellt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
	$scope.editClicked = function() {
		$scope.modifyEvent.start = $window.document.getElementById("start").value;
		$scope.modifyEvent.end = $window.document.getElementById("end").value;
		if ($scope.changed){
			$scope.modifyEvent.eventPictures = btoa(JSON.stringify($scope.eventPictures));
			HelperService.sendToServer('put','api/events/'+$scope.modifyEvent.eventid,$scope.modifyEvent,function(response){
				if(response.data.result == "success"){
					$rootScope.$emit(EVT_SHOW_OK, "Der Event wurde erfolgreich geändert.");
					$rootScope.$emit(EVT_SHOW_DIALOG, "");
					$route.reload();
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		} else {
			$rootScope.$emit(EVT_SHOW_OK, "Keine Änderung erkannt.");
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
		};
	};
	$scope.changed = false;
	if ($rootScope.actionType == 'create'){
		$scope.modifyEvent = {};
		$scope.eventPictures = [];
	} else {
		$scope.modifyEvent = Object.assign({},$rootScope.eventObject);
		$scope.modifyEvent.start_real = $scope.modifyEvent.start;
		$scope.modifyEvent.end_real = $scope.modifyEvent.end;
		$scope.eventPictures = JSON.parse(atob($scope.modifyEvent.pictures));
	}
	HelperService.updateFocus('title');
	$(function () {
        $('#datepicker_start').datetimepicker({
			locale: 'de-ch',
            stepping: 15,
			minDate: new Date()
		});
        $('#datepicker_end').datetimepicker({
			locale: 'de-ch',
            stepping: 15,
			minDate: new Date(),
            useCurrent: false
        });
        $("#datepicker_start").on("dp.change", function (e) {
            $('#datepicker_end').data("DateTimePicker").minDate(e.date);
			$scope.changed = true;
        });
        $("#datepicker_end").on("dp.change", function (e) {
            $('#datepicker_start').data("DateTimePicker").maxDate(e.date);
			$scope.changed = true;
        });
    });
});
socialEventApp.controller('deleteEventController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.noClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.yesClicked = function() {
		HelperService.sendToServer('delete','api/events/'+$scope.deleteEvent.eventid,{},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Der Event wurde erfolgreich entfernt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
	$scope.deleteEvent = Object.assign({},$rootScope.eventObject);
});
socialEventApp.controller('viewEventController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.backClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.editClicked = function(offerid){
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyEvent.html");
	};
	$scope.deleteClicked = function(offerid){
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteEvent.html");
	};
	$scope.showActivitiesClicked = function() {
		if ($scope.viewEvent.activityCount == 0){
			$rootScope.$emit(EVT_SHOW_ERROR, "Bisher wurden hierfür keine Aktivitäten erstellt.");
		} else {
			$location.path( "/activities" ).search({eventid: $scope.viewEvent.eventid});
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
		}
	}
	$scope.createActivityClicked = function() {
		$rootScope.eventObject = $scope.viewEvent;
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	}
	$scope.viewEvent = $rootScope.eventObject;
	$scope.eventPictures = JSON.parse(atob($scope.viewEvent.pictures));
	if ($scope.eventPictures.length == 0){
		$scope.eventPictures.push("img/noPicture.png");
	}
	HelperService.sendToServer('get','api/users/'+$scope.viewEvent.provider,{},function(response){
			if(response.data.result =="success"){
				$scope.viewEvent.providerDetails = response.data.profile;
			}
	});
});

socialEventApp.controller('activitiesController', function($scope, $http, $rootScope, $cookies, $location, $routeParams, $route, HelperService){
	HelperService.checkSession();
	
	$scope.showActivityClicked = function(activity) {
		$rootScope.eventObject=activity;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewActivity.html");
	}
	
	$scope.editActivityClicked = function(activity) {
		$rootScope.eventObject = activity;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	}
	
	$scope.deleteActivityClicked = function(activity) {
		$rootScope.eventObject = activity;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteActivity.html");
	}
	
	$scope.viewOfferClicked = function(activity) {
		$rootScope.eventObject = activity.offer;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewOffer.html");
	}
	
	$scope.viewEventClicked = function(activity) {
		$rootScope.eventObject = activity.event;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewEvent.html");
	}
	
	$scope.joinActivityClicked = function(activity) {
		HelperService.sendToServer('post','api/participations',{activityid: activity.activityid},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Sie haben sich für die Aktivität angemeldet.");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}
	
	$scope.leaveActivityClicked = function(activity) {
		HelperService.sendToServer('delete','api/participations',{activityid: activity.activityid},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Sie haben sich von der Aktivität abgemeldet.");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}
	
	var getActivities = function (){
		$scope.loading = true;
		$scope.activities = [];
		$scope.filtered = Object.keys($routeParams).length > 0;
	
		HelperService.sendToServer('get','api/activities',$routeParams,function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				if (response.data.activities) {
					$scope.activities = response.data.activities;
					for (var i = 0; i < $scope.activities.length; i++) {
						{
						let tmp = i;
						if ($scope.activities[i].offerid != null){
							HelperService.sendToServer('get','api/offers/'+$scope.activities[i].offerid,{},function(response){
								if(response.data.result == "success") {
									$scope.activities[tmp].offer = response.data.offers[0];
								} else {
									$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
								}
							});
						} else if ($scope.activities[i].eventid != null){
							HelperService.sendToServer('get','api/events/'+$scope.activities[i].eventid,{},function(response){
								if(response.data.result == "success") {
									$scope.activities[tmp].event = response.data.events[0];
								} else {
									$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
								}
							});
						}
						$scope.activities[i].activityPictures = JSON.parse(atob($scope.activities[i].pictures));
						if ($scope.activities[i].activityPictures.length == 0){
							$scope.activities[i].activityPictures.push("img/noPicture.png");
						}
						}
					}
				}
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getActivities();
});
socialEventApp.controller('myactivitiesController', function($scope, $http, $rootScope, $cookies, $location, HelperService){
	HelperService.checkSession();
	
	$scope.createActivityClicked = function(){
		$rootScope.eventObject = {};
		$rootScope.actionType = "create";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	};
	$scope.viewActivityClicked = function(activity){
		$rootScope.eventObject = activity;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewActivity.html");
	};
	$scope.editActivityClicked = function(activity){
		$rootScope.eventObject = activity;
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	};
	$scope.deleteActivityClicked = function(activity){
		$rootScope.eventObject = activity;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/deleteActivity.html");
	};
	var getMyActivities = function (){
		$scope.loading = true;
		$scope.myActivities = [];
		HelperService.sendToServer('get','api/activities',{profile:$rootScope.name},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.myActivities = response.data.activities;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getMyActivities();
});
socialEventApp.controller('modifyActivityController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.abortClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.deleteClicked = function(img) {
		var i = $scope.activityPictures.indexOf(img);
		if(i != -1) {
			$scope.activityPictures.splice(i, 1);
			$scope.changed=true;
		}
	};
	$scope.onPictureUpdate = function(){
		var f = $window.document.getElementById('file').files[0];
		if (f.type.toLowerCase() != 'image/png' && f.type.toLowerCase() != 'image/jpeg'){
			$rootScope.$emit(EVT_SHOW_ERROR, "Ausgewählter Dateityp ("+f.type+") nicht unterstützt. Nur *.jpg und *.png erlaubt.");
		} else {
			new ImageCompressor(f, {
				quality: 0.6,
				maxHeight: 440,
				success(result) {
					r = new FileReader();
					r.onloadend = function(e){
					if ($scope.activityPictures.length + 1 > 5){
						$rootScope.$emit(EVT_SHOW_ERROR, "Die maximale Anzahl an Bildern wurde erreicht!");
					} else if ($scope.activityPictures.indexOf('data:'+f.type+';base64,' + btoa(e.target.result)) != -1){
						$rootScope.$emit(EVT_SHOW_ERROR, "Das Bild ist bereits vorhanden!");
					} else {
						$scope.activityPictures.push('data:'+f.type+';base64,' + btoa(e.target.result));
						$scope.changed=true;
					}
					$scope.$apply();
					}
					r.readAsBinaryString(result);
				},
				error(e) {
				  $rootScope.$emit(EVT_SHOW_ERROR, e.message);
				},
			  });
		}
	$scope.$apply();
	}
	
	$scope.createClicked = function() {
		$scope.modifyActivity.activityPictures = btoa(JSON.stringify($scope.activityPictures));
		$scope.modifyActivity.start = $window.document.getElementById("start").value;
		HelperService.sendToServer('post','api/activities',$scope.modifyActivity,function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Die Aktivität wurde erfolgreich erstellt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
	$scope.editClicked = function() {
		$scope.modifyActivity.start = $window.document.getElementById("start").value;
		if ($scope.changed){
			$scope.modifyActivity.activityPictures = btoa(JSON.stringify($scope.activityPictures));
			HelperService.sendToServer('put','api/activities/'+$scope.modifyActivity.activityid,$scope.modifyActivity,function(response){
				if(response.data.result == "success"){
					$rootScope.$emit(EVT_SHOW_OK, "Die Aktivität wurde erfolgreich geändert.");
					$rootScope.$emit(EVT_SHOW_DIALOG, "");
					$route.reload();
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		} else {
			$rootScope.$emit(EVT_SHOW_OK, "Keine Änderung erkannt.");
			$rootScope.$emit(EVT_SHOW_DIALOG, "");
		};
	};
	$scope.changed = false;
	if ($rootScope.actionType == 'create'){
		$scope.modifyActivity = {};
		$scope.activityPictures = [];
		if (typeof $rootScope.eventObject.offerid != "undefined"){
			$scope.modifyActivity.offerid = $rootScope.eventObject.offerid;
			$scope.modifyActivity.title = $rootScope.eventObject.title;
			$scope.modifyActivity.description = $rootScope.eventObject.description;	
			$scope.activityPictures = JSON.parse(atob($rootScope.eventObject.pictures));
			$scope.linkedContent = $rootScope.eventObject.title + " von " + $rootScope.eventObject.name
		} else if (typeof $rootScope.eventObject.eventid != "undefined"){
			$scope.modifyActivity.eventid = $rootScope.eventObject.eventid;
			$scope.modifyActivity.title = $rootScope.eventObject.title;
			$scope.modifyActivity.description = $rootScope.eventObject.description;
			$scope.modifyActivity.start_real = $rootScope.eventObject.start;
			$scope.modifyActivity.start = $rootScope.eventObject.start;
			$scope.activityPictures = JSON.parse(atob($rootScope.eventObject.pictures));
			$scope.linkedContent = $rootScope.eventObject.title + " am " + $rootScope.eventObject.start;
		}
	} else {
		$scope.modifyActivity = Object.assign({},$rootScope.eventObject);
		$scope.modifyActivity.start_real = $scope.modifyActivity.start;
		$scope.activityPictures = JSON.parse(atob($scope.modifyActivity.pictures));
		if ($scope.modifyActivity.offerid != null){
			HelperService.sendToServer('get','api/offers/'+$scope.modifyActivity.offerid,{},function(response){
				if(response.data.result == "success"){
					offer = response.data.offers[0];
					$scope.linkedContent = offer.title + " von " + offer.name;				
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		} else if ($scope.modifyActivity.eventid != null){
			HelperService.sendToServer('get','api/events/'+$scope.modifyActivity.eventid,{},function(response){
				if(response.data.result == "success"){
					event = response.data.events[0];
					$scope.linkedContent = event.title + " am " + event.start;					
				} else {
					$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
				}
			});
		}
	}
	HelperService.updateFocus('title');
	$(function () {
        $('#datepicker_start').datetimepicker({
			locale: 'de-ch',
            stepping: 15,
			minDate: new Date()
		});
        $("#datepicker_start").on("dp.change", function (e) {
			$scope.changed = true;
        });
    });
});
socialEventApp.controller('deleteActivityController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.noClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.yesClicked = function() {
		HelperService.sendToServer('delete','api/activities/' + $scope.deleteActivity.activityid,{},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Die Aktivität wurde erfolgreich entfernt.");
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	};
	$scope.deleteActivity = Object.assign({},$rootScope.eventObject);
});
socialEventApp.controller('viewActivityController', function($scope, $http, $rootScope, $cookies, $location, $window, $route, HelperService){
	HelperService.checkSession();
	$scope.backClicked = function(){
		$rootScope.$emit(EVT_SHOW_DIALOG, "");
	};
	$scope.editClicked = function(){
		$rootScope.actionType = "edit";
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/modifyActivity.html");
	};
	$scope.viewOfferClicked = function() {
		$rootScope.eventObject = $scope.viewActivity.offer;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewOffer.html");
	}
	
	$scope.viewEventClicked = function() {
		$rootScope.eventObject = $scope.viewActivity.event;
		$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewEvent.html");
	}
	
	$scope.joinActivityClicked = function() {
		HelperService.sendToServer('put','api/participations',{activityid: $scope.viewActivity.activityid},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
				$rootScope.$emit(EVT_SHOW_OK, "Sie haben sich für die Aktivität angemeldet.");
				$scope.viewActivity.participated=true;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}
	
	$scope.leaveActivityClicked = function(activity) {
		HelperService.sendToServer('delete','api/participations',{activityid: $scope.viewActivity.activityid},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_DIALOG, "");
				$route.reload();
				$rootScope.$emit(EVT_SHOW_OK, "Sie haben sich von der Aktivität abgemeldet.");
				$scope.viewActivity.participated=false;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}

	$scope.viewActivity = $rootScope.eventObject;
	$scope.activityPictures = JSON.parse(atob($scope.viewActivity.pictures));
	if ($scope.activityPictures.length == 0){
		$scope.activityPictures.push("img/noPicture.png");
	}
});

socialEventApp.controller('myparticipationsController', function($scope, $http, $rootScope, $cookies, $location, $route, HelperService){
	HelperService.checkSession();
	$scope.viewActivityClicked = function(activityid){
		HelperService.sendToServer('get','api/activities/'+activityid,{},function(response){
			if(response.data.result == "success"){
				$rootScope.eventObject = response.data.activities[0];
				$rootScope.$emit(EVT_SHOW_DIALOG, "partial/viewActivity.html");
				if (response.data.activities[0].offerid != null){
					HelperService.sendToServer('get','api/offers/'+response.data.activities[0].offerid,{},function(response){
						if(response.data.result == "success") {
							$rootScope.eventObject.offer = response.data.offers[0];
						} else {
							$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
						}
					});
				} else if (response.data.activities[0].eventid != null){
					HelperService.sendToServer('get','api/events/'+response.data.activities[0].eventid,{},function(response){
						if(response.data.result == "success") {
							$rootScope.eventObject.event = response.data.events[0];
						} else {
							$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
						}
					});
				}
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
		
	};
	$scope.leaveActivityClicked = function(activityid) {
		HelperService.sendToServer('delete','api/participations',{activityid: activityid},function(response){
			if(response.data.result == "success"){
				$rootScope.$emit(EVT_SHOW_OK, "Sie haben sich von der Aktivität abgemeldet.");
				$route.reload();
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
		});
	}
	var getMyParticipations = function (){
		$scope.loading = true;
		$scope.myParticipations = [];
		HelperService.sendToServer('get','api/participations',{},function(response){
			if(response.data.result =="success"){
				$scope.loading=false;
				$scope.myParticipations = response.data.participations;
			} else {
				$rootScope.$emit(EVT_SHOW_ERROR, response.data.errormessage);
			}
			});
	};
	getMyParticipations();
});