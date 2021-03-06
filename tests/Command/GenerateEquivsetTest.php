<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace Wikimedia\Equivset\Command;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generate Equivset Command.
 */
class GenerateEquivsetTest extends TestCase {

	/**
	 * Test Configuration
	 */
	public function testConfigure() {
		$command = new GenerateEquivset();

		$this->assertEquals( 'generate-equivset', $command->getName() );
	}

	/**
	 * Test Mocked Execute.
	 */
	public function testExecute() {
		$in = "# Testing...\n30 0 => 4F O";
		$out = [
			0 => 'O',
		];

		$root = vfsStream::setup();
		$data = vfsStream::newDirectory( 'data' )
			->at( $root );
		$file = vfsStream::newFile( 'equivset.in' )
			->withContent( $in )
			->at( $data );
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( $data->url(), $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();

		$status = $command->execute( $input, $output );

		$this->assertSame( 0, $status );

		$this->assertTrue( $dist->hasChild( 'equivset.ser' ) );
		$this->assertTrue( $dist->hasChild( 'equivset.json' ) );
		$this->assertTrue( $dist->hasChild( 'equivset.txt' ) );

		$this->assertEquals( serialize( $out ), $dist->getChild( 'equivset.ser' )->getContent() );
	}

	/**
	 * Test Live Execute.
	 */
	public function testLiveExecute() {
		// Write the output to memory.
		$root = vfsStream::setup();
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( '', $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();

		$status = $command->execute( $input, $output );

		$this->assertSame( 0, $status );

		$this->assertTrue( $dist->hasChild( 'equivset.ser' ) );
		$this->assertTrue( $dist->hasChild( 'equivset.json' ) );
		$this->assertTrue( $dist->hasChild( 'equivset.txt' ) );

		$output = unserialize( $dist->getChild( 'equivset.ser' )->getContent() );

		$this->assertEquals( 'O', $output[0] );
	}

	/**
	 * Test Execute Fail Open
	 */
	public function testExecuteFailOpen() {
		$root = vfsStream::setup();
		$data = vfsStream::newDirectory( 'data' )
			->at( $root );
		$file = vfsStream::newFile( 'equivset.in' )
			->at( $data );
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( $data->url(), $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unable to open equivset.in' );
		$command->execute( $input, $output );
	}

	/**
	 * Test Execute Fail Malformed
	 *
	 * Ensure that malformed input data results in a failure of the
	 * generate-equivset command.
	 */
	public function testExecuteFailMalformed() {
		$in = "0 => 4F O";
		$out = [
			0 => 'O',
		];

		$root = vfsStream::setup();
		$data = vfsStream::newDirectory( 'data' )
			->at( $root );
		$file = vfsStream::newFile( 'equivset.in' )
			->withContent( $in )
			->at( $data );
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( $data->url(), $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();
		$output->method( 'writeln' )
			->with( $this->logicalOr(
				$this->stringContains( 'Error: invalid entry' ),
				$this->stringContains( 'Finished with errors' )
			) );
		$status = $command->execute( $input, $output );

		$this->assertSame( 1, $status );

		$this->assertNotEquals( serialize( $out ), $dist->getChild( 'equivset.ser' )->getContent() );
	}

	/**
	 * Provide Not Matching Code Points.
	 */
	public function provideNotMatchingCodePoints() {
		return [
			[ 'left', '31', '31 0 => 4F O' ],
			[ 'right', '4', '30 0 => 4 O' ],
		];
	}

	/**
	 * Test Execute Fail Not Matching Codepoint
	 *
	 * Ensure that code points and letters not matching results in a failure of
	 * the generate-equivset command.
	 *
	 * @param string $side The left or right side
	 * @param string $number N being used
	 * @param string $in Equivset line
	 *
	 * @dataProvider provideNotMatchingCodePoints
	 */
	public function testExecuteFailNotMatchingCodepoint( $side, $number, $in ) {
		$out = [
			0 => 'O',
		];

		$root = vfsStream::setup();
		$data = vfsStream::newDirectory( 'data' )
			->at( $root );
		$file = vfsStream::newFile( 'equivset.in' )
			->withContent( $in )
			->at( $data );
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( $data->url(), $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();
		$output->method( 'writeln' )
			->with( $this->logicalOr(
				$this->stringContains( "Error: $side number ($number) does not match" ),
				$this->stringContains( 'Finished with errors' )
			) );

		$status = $command->execute( $input, $output );

		$this->assertSame( 1, $status );

		$this->assertNotEquals( serialize( $out ), $dist->getChild( 'equivset.ser' )->getContent() );
	}

	/**
	 * Provide Invalid Chars
	 */
	public function provideInvalidChar() {
		return [
			[ '30 �� => 4F O' ],
			[ '30 0 => 4F ��' ],
		];
	}

	/**
	 * Test Execute Failure Invalid Charachter
	 *
	 * Ensure that invalid UTF-8 characters results in a failure of the
	 * generate-equivset command.
	 *
	 * @param string $in Equivset line
	 *
	 * @dataProvider provideInvalidChar
	 */
	public function testExecuteFailInvalidChar( $in ) {
		$out = [
			0 => 'O',
		];

		$root = vfsStream::setup();
		$data = vfsStream::newDirectory( 'data' )
			->at( $root );
		$file = vfsStream::newFile( 'equivset.in' )
			->withContent( $in )
			->at( $data );
		$dist = vfsStream::newDirectory( 'dist' )
			->at( $root );

		$command = new GenerateEquivset( $data->url(), $dist->url() );

		$input = $this->getMockBuilder( InputInterface::class )
			->getMock();
		$output = $this->getMockBuilder( OutputInterface::class )
			->getMock();
		$output->method( 'writeln' )
			->with( $this->logicalOr(
				$this->stringContains( 'Bytes' ),
				$this->stringContains( bin2hex( $in ) ),
				$this->stringContains( 'Invalid UTF-8 character' ),
				$this->stringContains( 'Finished with errors' )
			) );

		$status = $command->execute( $input, $output );

		$this->assertSame( 1, $status );

		$this->assertNotEquals( serialize( $out ), $dist->getChild( 'equivset.ser' )->getContent() );
	}
}
