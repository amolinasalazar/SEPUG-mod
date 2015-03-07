
M.mod_sepug = {};

M.mod_sepug.init = function(Y) {
    if (document.getElementById('surveyform')) {
        var surveyform = document.getElementById('surveyform');
        Y.YUI2.util.Event.addListener('surveyform', "submit", function(e) {
            var error = false;
            if (document.getElementById('surveyform')) {
                var surveyform = document.getElementById('surveyform');
                for (var i=0; i < surveycheck.questions.length; i++) {
                    var tempquestion = surveycheck.questions[i];
					// Si las preguntas radio o selected estan sin contestar, error
                    if (surveyform[tempquestion['question']][tempquestion['default']].checked || surveyform[tempquestion['question']][tempquestion['default']].selected) {
                        error = true;
                    }
                }
            }
            if (error) {
				alert(M.util.get_string('questionsnotanswered', 'sepug'));
                Y.YUI2.util.Event.preventDefault(e);
                return false;
            } else {
                return true;
            }
        });
    }
};
