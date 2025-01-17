<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Further walkthrough tests for the CodeRunner plugin, testing recently
 * added features like the 'extra' field for use by the template and the
 * relabelling of output columns.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2014 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

define('PRELOAD_TEST', "# TEST COMMENT TO CHECK PRELOAD IS WORKING\n");

class qtype_coderunner_walkthrough_extras_test extends qbehaviour_walkthrough_test_base {

    protected function setUp() {
        global $CFG;
        parent::setUp();
        qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    public function test_extra_testcase_field() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->testcases = array(
            (object) array('type'     => 0,
                          'testcode'  => 'print("Oops")',
                          'extra'     => 'print(sqr(-11))',
                          'expected'  => '121',
                          'stdin'     => '',
                          'useasexample' => 0,
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0)
            );
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
{{ TEST.extra }}  # Use this instead of the normal testcode field
EOTEMPLATE;
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $q->answerpreload = PRELOAD_TEST;

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_current_output( new question_pattern_expectation('/' . PRELOAD_TEST . '/') );
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(1.0);
    }

    public function test_result_column_selection() {
        // Make sure can relabel result table columns.
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->resultcolumns = '[["Blah", "testcode"], ["Thing", "expected"], ["Gottim", "got"]]';

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(1.0);
        $this->check_current_output( new question_pattern_expectation('/Blah/') );
        $this->check_current_output( new question_pattern_expectation('/Thing/') );
        $this->check_current_output( new question_pattern_expectation('/Gottim/') );
    }

    /** Make sure that if the Jobe URL is wrong we get "jobesandbox is down
     *  or misconfigured" exception.
     *
     * @expectedException qtype_coderunner_exception
     * @expectedExceptionMessageRegExp |.*Error from the sandbox.*may be down or overloaded.*|
     * @retrun void
     */
    public function test_misconfigured_jobe() {
        if (!get_config('qtype_coderunner', 'jobesandbox_enabled')) {
            $this->markTestSkipped("Sandbox $sandbox unavailable: test skipped");
        }
        set_config('jobe_host', 'localhostxxx', 'qtype_coderunner');  // Broken jobe_host url.
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
    }

    /** Check that a combinator template is run once per test case when stdin
     *  is present and allowmultiplestdins is false, but run with all test
     *  cases when allowmutliplestdins is true.
     */
    public function test_multiplestdins() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->testcases[0]->stdin = 'A bit of standard input to trigger one-at-a-time mode';
        $q->showsource = true;

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_output_contains('Run 4');

        // Now turn on allowmultiplestdins and try again.
        $q->allowmultiplestdins = true;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_output_does_not_contain('Run 4');
    }

    /** Check that the triple-quoted string extension to the JSON syntax used in the
     *  the template parameters allows multiline strings with embedded double
     *  quotes to be correctly and transparently converted to true JSON strings.
     */
    public function test_template_params_with_triplequotes() {
        // Test that a templateparams field in the question with triple-quoted
        // strings is converted to a valid JSON string and used
        // correctly in the template.

        $q = $this->make_question('sqr');
        $q->templateparams = <<<EOTEMPLATEPARAMS
{
    "string1": """Line1
Line2
He said "Hi!",
    "string2": """import thing
print("Look at me.")
"""
}
EOTEMPLATEPARAMS;

        $q->template = <<<EOTEMPLATE
print('{{string1}}')
print("+++")
print('{{string2}}')
print("---")
EOTEMPLATE;
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $q->testcases = array(
                       (object) array('type' => 0,
                         'testcode'       => '',
                         'expected'       => <<<EOEXPECTED
Line1
Line2
He said "Hi!"
+++
import thing
print("Look at me.")
EOEXPECTED
,
                         'stdin'          => '',
                         'extra'          => '',
                         'useasexample'   => 0,
                         'display'        => 'SHOW',
                         'mark'           => 1.0,
                         'hiderestiffail' => 0),
        );
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $code = "";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedright, $grade);
    }
}
