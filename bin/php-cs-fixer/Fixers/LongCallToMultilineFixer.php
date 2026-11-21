<?php
/**
 * Split function and method calls that make a line longer than the limit.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\PHPCSFixer\Fixers;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * A call on a line over the limit is broken across lines.
 *
 * This only inserts a newline after the opening parenthesis. `method_argument_space`, which
 * runs after it, is what puts one argument per line and indents them — hence the priority
 * above that fixer's 30. Doing it this way means there is one implementation of the argument
 * formatting rather than two that can disagree.
 */
final class LongCallToMultilineFixer extends AbstractFixer {
	/** Lines longer than this are candidates. */
	private const LIMIT = 80;

	public function getDefinition(): FixerDefinitionInterface {
		return new FixerDefinition(
			'Function and method calls on lines longer than ' . self::LIMIT . ' characters must be split across lines.',
			array(
				new CodeSample(
					"<?php\nwp_enqueue_style( 'example-plugin-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), '1.0.0' );\n",
				),
			),
		);
	}

	/**
	 * Custom fixers must carry a vendor prefix, which AbstractFixer does not add.
	 */
	public function getName(): string {
		return 'BrianHenryIE/long_call_to_multiline';
	}

	public function isCandidate( Tokens $tokens ): bool {
		return $tokens->isTokenKindFound( '(' );
	}

	public function getPriority(): int {
		// Above method_argument_space (30) so it formats what this splits.
		return 40;
	}

	protected function applyFix( SplFileInfo $file, Tokens $tokens ): void {
		// Work backwards so inserting tokens does not shift indexes still to be examined.
		for ( $index = $tokens->count() - 1; 0 <= $index; --$index ) {
			if ( ! $tokens[ $index ]->equals( '(' ) ) {
				continue;
			}

			if ( ! $this->is_call( $tokens, $index ) ) {
				continue;
			}

			$close = $tokens->findBlockEnd( Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index );

			// Already split, or empty argument list — nothing to gain.
			if ( $index + 1 === $close || $this->spans_lines( $tokens, $index, $close ) ) {
				continue;
			}

			if ( ! $this->line_is_too_long( $tokens, $index ) ) {
				continue;
			}

			// Only the outermost call on the line: a nested one is shortened by splitting its parent.
			if ( $this->is_nested_in_single_line_call( $tokens, $index ) ) {
				continue;
			}

			/*
			 * A `<?php … ?>` island inside markup. Splitting one leaves the opening tag stranded
			 * at the end of a line of HTML and the arguments unindentable, which reads worse than
			 * the long line did.
			 */
			if ( $this->line_has_inline_html( $tokens, $index ) ) {
				continue;
			}

			// One argument on its own line is churn, not clarity.
			if ( 2 > $this->count_arguments( $tokens, $index, $close ) ) {
				continue;
			}

			/*
			 * If everything up to the parenthesis already exceeds the limit, the arguments are not
			 * what makes the line long and splitting them will not bring it under.
			 */
			if ( self::LIMIT <= $this->prefix_length( $tokens, $index ) ) {
				continue;
			}

			/*
			 * Only a call that is the whole statement. Embedded in a concatenation, a ternary or
			 * an array value, splitting reads badly, and where the surrounding expression already
			 * spans lines php-cs-fixer and phpcbf indent the result differently and revert each
			 * other on every run.
			 */
			if ( ! $this->is_whole_statement( $tokens, $index, $close ) ) {
				continue;
			}

			/*
			 * WordPress style puts a space after the parenthesis. Replace that space rather than
			 * inserting before it, or the first argument ends up indented one column too far.
			 */
			if ( $tokens[ $index + 1 ]->isGivenKind( T_WHITESPACE ) ) {
				$tokens[ $index + 1 ] = new Token( array( T_WHITESPACE, "\n" ) );
			} else {
				$tokens->insertAt( $index + 1, new Token( array( T_WHITESPACE, "\n" ) ) );
			}
		}
	}

	/**
	 * Is the parenthesis at $index opening a call, rather than a declaration or control structure?
	 */
	private function is_call( Tokens $tokens, int $index ): bool {
		$before = $tokens->getPrevMeaningfulToken( $index );

		if ( null === $before ) {
			return false;
		}

		// `foo(`, `$foo(`, `Foo::bar(`, `$foo->bar(`, `}(`… but not `function foo(`.
		if ( ! $tokens[ $before ]->isGivenKind( array( T_STRING, T_VARIABLE ) )
			&& ! $tokens[ $before ]->equals( ')' )
			&& ! $tokens[ $before ]->equals( ']' ) ) {
			return false;
		}

		$before_name = $tokens->getPrevMeaningfulToken( $before );

		if ( null !== $before_name
			&& $tokens[ $before_name ]->isGivenKind( array( T_FUNCTION, T_FN, T_CLASS, T_CONST ) ) ) {
			return false;
		}

		// Control structures take a parenthesis but are not calls.
		if ( $tokens[ $before ]->isGivenKind( T_STRING )
			&& in_array(
				strtolower( $tokens[ $before ]->getContent() ),
				array( 'if', 'elseif', 'while', 'for', 'foreach', 'switch', 'catch', 'match', 'declare', 'list', 'isset', 'unset', 'empty', 'array' ),
				true,
			) ) {
			return false;
		}

		return true;
	}

	/**
	 * Does the token range already contain a newline?
	 */
	private function spans_lines( Tokens $tokens, int $start, int $end ): bool {
		for ( $i = $start; $i <= $end; ++$i ) {
			if ( str_contains( $tokens[ $i ]->getContent(), "\n" ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is the physical line holding $index longer than the limit?
	 */
	private function line_is_too_long( Tokens $tokens, int $index ): bool {
		$count = $tokens->count();

		// The token holding the newline that starts this line, and the one that ends it.
		$start = $index;
		while ( 0 < $start && ! str_contains( $tokens[ $start ]->getContent(), "\n" ) ) {
			--$start;
		}

		$end = $index;
		while ( $end < $count - 1 && ! str_contains( $tokens[ $end ]->getContent(), "\n" ) ) {
			++$end;
		}

		// Those two tokens are partly on neighbouring lines, so take only the relevant side.
		$line = '';
		for ( $i = $start; $i <= $end; ++$i ) {
			$content = $tokens[ $i ]->getContent();

			if ( $i === $start ) {
				$nl      = strrpos( $content, "\n" );
				$content = false === $nl ? $content : substr( $content, $nl + 1 );
			}

			if ( $i === $end ) {
				$nl      = strpos( $content, "\n" );
				$content = false === $nl ? $content : substr( $content, 0, $nl );
			}

			$line .= $content;
		}

		return self::LIMIT < strlen( $line );
	}

	/**
	 * Is the call the entire statement, rather than part of a larger expression?
	 *
	 * The call must end the statement (`);`) and the token starting it must follow a statement
	 * boundary, so `foo( … );` qualifies but `$x = foo( … ) . 'y';` and `'k' => foo( … ),` do not.
	 */
	private function is_whole_statement( Tokens $tokens, int $open, int $close ): bool {
		$after = $tokens->getNextMeaningfulToken( $close );

		if ( null === $after || ! $tokens[ $after ]->equals( ';' ) ) {
			return false;
		}

		// Walk back over the callee: name, `->`, `::`, variables, namespace separators.
		$start = $tokens->getPrevMeaningfulToken( $open );
		while ( null !== $start
			&& $tokens[ $start ]->isGivenKind(
				array( T_STRING, T_VARIABLE, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR, T_NEW, T_STATIC ),
			) ) {
			$start = $tokens->getPrevMeaningfulToken( $start );
		}

		if ( null === $start ) {
			return true;
		}

		/*
		 * `:` is deliberately absent. It would admit the else branch of a ternary —
		 * `$x = $c ? '' : foo( … );` — where splitting leaves the call dangling off the ternary.
		 * The cost is skipping a call that directly follows a `case:` label.
		 */
		return $tokens[ $start ]->equalsAny( array( ';', '{', '}', array( T_OPEN_TAG ) ) );
	}

	/**
	 * Does the line hold markup or a PHP open/close tag?
	 */
	private function line_has_inline_html( Tokens $tokens, int $index ): bool {
		foreach ( $this->line_range( $tokens, $index ) as $i ) {
			if ( $tokens[ $i ]->isGivenKind( array( T_INLINE_HTML, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * How many arguments does the call take?
	 */
	private function count_arguments( Tokens $tokens, int $open, int $close ): int {
		if ( $open + 1 === $close ) {
			return 0;
		}

		$depth = 0;
		$count = 1;

		for ( $i = $open + 1; $i < $close; ++$i ) {
			if ( $tokens[ $i ]->equalsAny( array( '(', '[' ) ) || $tokens[ $i ]->isGivenKind( CT::T_ARRAY_SQUARE_BRACE_OPEN ) ) {
				++$depth;
			} elseif ( $tokens[ $i ]->equalsAny( array( ')', ']' ) ) || $tokens[ $i ]->isGivenKind( CT::T_ARRAY_SQUARE_BRACE_CLOSE ) ) {
				--$depth;
			} elseif ( 0 === $depth && $tokens[ $i ]->equals( ',' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Length of the line up to and including the opening parenthesis.
	 */
	private function prefix_length( Tokens $tokens, int $index ): int {
		$length = 0;

		foreach ( $this->line_range( $tokens, $index ) as $i ) {
			if ( $i > $index ) {
				break;
			}

			$content = $tokens[ $i ]->getContent();
			$nl      = strrpos( $content, "\n" );
			$length  = false === $nl ? $length + strlen( $content ) : strlen( $content ) - $nl - 1;
		}

		return $length;
	}

	/**
	 * Indexes of the tokens making up the physical line holding $index.
	 *
	 * @return list<int>
	 */
	private function line_range( Tokens $tokens, int $index ): array {
		$count = $tokens->count();

		$start = $index;
		while ( 0 < $start && ! str_contains( $tokens[ $start ]->getContent(), "\n" ) ) {
			--$start;
		}

		$end = $index;
		while ( $end < $count - 1 && ! str_contains( $tokens[ $end ]->getContent(), "\n" ) ) {
			++$end;
		}

		return range( $start, $end );
	}

	/**
	 * Is this call an argument of another call that is itself still on one line?
	 */
	private function is_nested_in_single_line_call( Tokens $tokens, int $index ): bool {
		$depth = 0;

		for ( $i = $index - 1; 0 <= $i; --$i ) {
			if ( str_contains( $tokens[ $i ]->getContent(), "\n" ) ) {
				return false;
			}
			if ( $tokens[ $i ]->equals( ')' ) ) {
				++$depth;
				continue;
			}
			if ( $tokens[ $i ]->equals( '(' ) ) {
				if ( 0 === $depth ) {
					return $this->is_call( $tokens, $i );
				}
				--$depth;
			}
		}

		return false;
	}
}
