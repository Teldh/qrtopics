# qrtopics
Moodle Quiz Related Topics

The plugin provides the creation of a course with a certain number of topics whose visualization is linked to the result of an initial quiz of assessment / self assessment of the user’s competences.

Specifically, a new course format was implemented for Moodle platform capable of create, based on a number course topics given by input, a course similar to a course created through the topic format, with the addition of a Quiz of self-assessment with the function of controlling students’ viewing and access to the various topics of the course taking advantage of the possibility of Moodle to condition access to the topics in relation to certain scores obtained by the student performing certain activities belonging to the course.

The new course format, in the process of creating the course itself, automatically creates the desired sections, a self-assessment quiz (with a title, a true / false question for each course topic with the appropriate score for the restriction already set ), and conditions the access to topics based on this quiz.

To achieve this result, in addition to the study of the structure of Moodle and its databases, it was necessary to study an appropriate system of question’s scores in order to make unequivocal the contribution of a single question within the total score of the quiz and an algorithm calculation of the restrictions to be applied to the different topics.
