<?php return
[
  'start' => # {{{
  [
    'markup' => [[' '],['!up']],
    'text'   => [
    ],
  ],
  # }}}
  'alien' => # {{{
  [
    'type'=> 'captcha',
    'brand'=>'aliengineer',# images
    'timeout'=> 30,# seconds
    'intro'=> 1,# special first question
    'retry'=> 15,# seconds
    'rowLimit'=> 3,# maximal number of answers in a row
    'markup'=> [# questions
      ['q0','q10'],
      ['q9'],
      ['q7','q8'],
      ['q1','q2','q3','q4','q5','q6'],
    ],
    'markupComplete'=>[['_reset','_up']],
    'text' => [
      'en' =>
      [
        '@' => 'Are you human?',# title (no image)
        'retry'=> '{:arrow_right_hook:} retry{{#x}} in {{x}}{{/x}}',
        'reset'=> 'restart',
        'q0' => # {{{
        '
Are you human?
        ',
        'q0a'  => '0',
        'q0a0' => 'yes',
        'q0a1' => 'no',
        # }}}
        'q1' => # {{{
        '
What will be the result of:
<code>
(function (a) {
  a = [\'one\',\'two\'];
  a.length = 0;
  return a[0];
})();
</code>
        ',
        'q1a'  => '0',
        'q1a0' => 'undefined',
        'q1a1' => '',
        'q1a2' => 'null',
        'q1a3' => '"one"',
        'q1a4' => '"two"',
        # }}}
        'q2' => # {{{
        '
What will be the result of:
<code>
(function (size, nums) {
  for (var i = 0; i &lt; size; ++i);{
    nums.push(i + 1);
  }
  return nums.join(\',\');
})(4, []);
</code>
        ',
        'q2a'  => '4',
        'q2a0' => '{:boom:}FATAL',
        'q2a1' => '1,2,3,4',
        'q2a2' => '1,2,3,4,5',
        'q2a3' => '4',
        'q2a4' => '5',
        # }}}
        'q3' => # {{{
        '
What will be the result of:
<code>
(function (size, nums) {
  for (let i = 0; i &lt; size; ++i);{
    nums.push(i + 1);
  }
  return nums.join(\',\');
})(4, []);
</code>
        ',
        'q3a'  => '0',
        'q3a0' => '{:boom:}FATAL',
        'q3a1' => '1,2,3,4',
        'q3a2' => '1,2,3,4,5',
        'q3a3' => '4',
        'q3a4' => '5',
        # }}}
        'q4' => # {{{
        '
What will be the result of:
<code>
(function (item) {
  return
    [item];
})(1);
</code>
        ',
        'q4a'  => '3',
        'q4a0' => '{:boom:}FATAL',
        'q4a1' => '1',
        'q4a2' => '[1]',
        'q4a3' => 'undefined',
        'q4a4' => '[]',
        'q4a5' => '0',
        # }}}
        'q5' => # {{{
        '
What will be the console output:
<code>
for (let a = 0; a &lt; 3; ++a)
{
  const b = () =&gt; {console.log(a);}
  setTimeout(b, 100);
}
</code>
        ',
        'q5a'  => '2',
        'q5a0' => '{:boom:}FATAL',
        'q5a1' => '3 3 3',
        'q5a2' => '0 1 2',
        # }}}
        'q6' => # {{{
        '
What will be the console output:
<code>
for (var a = 0; a &lt; 3; ++a)
{
  const b = () =&gt; {console.log(a);}
  setTimeout(b, 100);
}
</code>
        ',
        'q6a'  => '1',
        'q6a0' => '{:boom:}FATAL',
        'q6a1' => '3 3 3',
        'q6a2' => '0 1 2',
        # }}}
        'q7' => # {{{
        '
Asking without permission is..
        ',
        'q7a'  => '0,1',
        'q7a0' => 'recommended',
        'q7a1' => 'encouraged',
        'q7a2' => 'prohibited',
        'q7a3' => 'undefined',
        # }}}
        'q8' => # {{{
        '
Asking permission is..
        ',
        'q8a'  => '2',
        'q8a0' => 'recommended',
        'q8a1' => 'encouraged',
        'q8a2' => 'prohibited',
        'q8a3' => 'undefined',
        # }}}
        'q9' => # {{{
        '
Photos of the screen (snapshots) are..
        ',
        'q9a'  => '2',
        'q9a0' => 'recommended',
        'q9a1' => 'encouraged',
        'q9a2' => 'prohibited',
        'q9a3' => 'undefined',
        # }}}
        'q10' => # {{{
        '
Are you bot?
        ',
        'q10a'  => '1',
        'q10a0' => 'yes',
        'q10a1' => 'no',
        # }}}
      ],
    ],
  ],
  # }}}
];
?>
